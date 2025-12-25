FROM wordpress:php8.2-apache

# --------------------------------------------------
# System packages
# --------------------------------------------------
RUN apt-get update && apt-get install -y \
    curl unzip less mariadb-client \
 && rm -rf /var/lib/apt/lists/*

# --------------------------------------------------
# PHP DEFAULTS (REAL override)
# --------------------------------------------------
RUN set -eux; \
    PHP_INI="$(php -r 'echo php_ini_loaded_file();')"; \
    { \
      echo ""; \
      echo "; ===== Coonex PHP Defaults ====="; \
      echo "upload_max_filesize = 20M"; \
      echo "post_max_size = 25M"; \
      echo "memory_limit = 256M"; \
      echo "max_execution_time = 300"; \
      echo "max_input_time = 300"; \
    } >> "$PHP_INI"

# --------------------------------------------------
# WP-CLI
# --------------------------------------------------
RUN curl -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
 && chmod +x /usr/local/bin/wp

# --------------------------------------------------
# Inject Coonex config into wp-config-sample.php
# --------------------------------------------------
RUN sed -i "/require_once ABSPATH . 'wp-settings.php';/i \
/** ==============================\\n\
 * Coonex URL & Proxy Detection (NO FORCE HTTPS)\\n\
 * ============================== */\\n\
if (getenv('WP_URL')) {\\n\
    define('WP_HOME', getenv('WP_URL'));\\n\
    define('WP_SITEURL', getenv('WP_URL'));\\n\
}\\n\\n\
if (!empty(\$_SERVER['HTTP_X_FORWARDED_PROTO'])) {\\n\
    \$_SERVER['HTTPS'] = \$_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ? 'on' : 'off';\\n\
}\\n" /usr/src/wordpress/wp-config-sample.php

# --------------------------------------------------
# Copy themes / plugins / MU plugins
# --------------------------------------------------
COPY assets/themes/ /usr/src/wordpress/wp-content/themes/
COPY assets/plugins/ /usr/src/wordpress/wp-content/plugins/
COPY assets/mu-plugins/ /usr/src/wordpress/wp-content/mu-plugins/

# --------------------------------------------------
# Entrypoint
# --------------------------------------------------
COPY docker-entrypoint-init.sh /usr/local/bin/docker-entrypoint-init.sh
RUN chmod +x /usr/local/bin/docker-entrypoint-init.sh

ENTRYPOINT ["/usr/local/bin/docker-entrypoint-init.sh"]
CMD ["apache2-foreground"]
