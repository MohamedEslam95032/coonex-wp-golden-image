FROM wordpress:php8.2-apache

RUN apt-get update && apt-get install -y \
    curl unzip less mariadb-client \
 && rm -rf /var/lib/apt/lists/*

RUN curl -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
 && chmod +x /usr/local/bin/wp

COPY assets/themes/ /usr/src/wordpress/wp-content/themes/
COPY assets/plugins/ /usr/src/wordpress/wp-content/plugins/
COPY assets/mu-plugins/ /usr/src/wordpress/wp-content/mu-plugins/

COPY docker-entrypoint-init.sh /usr/local/bin/docker-entrypoint-init.sh
RUN chmod +x /usr/local/bin/docker-entrypoint-init.sh

ENTRYPOINT ["/usr/local/bin/docker-entrypoint-init.sh"]
CMD ["apache2-foreground"]
