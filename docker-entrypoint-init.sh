#!/bin/bash
set -e

echo "▶ Starting Coonex WordPress Init Script"

#######################################
# 1) Wait for Database (with timeout)
#######################################
echo "⏳ Waiting for database (max 60s)..."

ATTEMPTS=0
MAX_ATTEMPTS=30

until mariadb \
  -h"${WORDPRESS_DB_HOST}" \
  -u"${WORDPRESS_DB_USER}" \
  -p"${WORDPRESS_DB_PASSWORD}" \
  -e "SELECT 1" >/dev/null 2>&1; do

  ATTEMPTS=$((ATTEMPTS+1))
  echo "⏳ DB not ready yet ($ATTEMPTS/$MAX_ATTEMPTS)"

  if [ "$ATTEMPTS" -ge "$MAX_ATTEMPTS" ]; then
    echo "❌ ERROR: Database not reachable after 60 seconds"
    exit 1
  fi

  sleep 2
done

echo "✅ Database is reachable"

#######################################
# 2) Ensure database exists
#######################################
echo "▶ Ensuring database exists..."

mariadb \
  -h"${WORDPRESS_DB_HOST}" \
  -u"${WORDPRESS_DB_USER}" \
  -p"${WORDPRESS_DB_PASSWORD}" \
  -e "CREATE DATABASE IF NOT EXISTS \`${WORDPRESS_DB_NAME}\`
      DEFAULT CHARACTER SET utf8mb4
      COLLATE utf8mb4_unicode_ci;"

#######################################
# 3) Copy WordPress core (NO rsync)
#######################################
if [ ! -f /var/www/html/wp-load.php ]; then
  echo "▶ Copying WordPress core to /var/www/html"
  cp -a /usr/src/wordpress/. /var/www/html/
  chown -R www-data:www-data /var/www/html
else
  echo "ℹ WordPress core already exists"
fi

#######################################
# 4) Create wp-config.php
#######################################
if [ ! -f /var/www/html/wp-config.php ]; then
  echo "▶ Creating wp-config.php"

  wp config create \
    --dbname="${WORDPRESS_DB_NAME}" \
    --dbuser="${WORDPRESS_DB_USER}" \
    --dbpass="${WORDPRESS_DB_PASSWORD}" \
    --dbhost="${WORDPRESS_DB_HOST}" \
    --skip-check \
    --allow-root \
    --path=/var/www/html

  wp config set DISALLOW_FILE_EDIT true --raw --allow-root --path=/var/www/html
  wp config set DISALLOW_FILE_MODS true --raw --allow-root --path=/var/www/html
  wp config set AUTOMATIC_UPDATER_DISABLED true --raw --allow-root --path=/var/www/html
else
  echo "ℹ wp-config.php already exists"
fi

#######################################
# 5) Install WordPress (once only)
#######################################
if ! wp core is-installed --allow-root --path=/var/www/html; then
  echo "▶ Installing WordPress"

  wp core install \
    --url="${WP_URL}" \
    --title="${WP_TITLE}" \
    --admin_user="${WP_ADMIN_USER}" \
    --admin_password="${WP_ADMIN_PASS}" \
    --admin_email="${WP_ADMIN_EMAIL}" \
    --skip-email \
    --allow-root \
    --path=/var/www/html

  echo "▶ Activating Coonex theme"
  wp theme activate "${COONEX_THEME_SLUG}" --allow-root --path=/var/www/html || true

  echo "▶ Activating allowed plugins"
  for plugin in ${COONEX_PLUGINS}; do
    wp plugin activate "$plugin" --allow-root --path=/var/www/html || true
  done
else
  echo "ℹ WordPress already installed – skipping install"
fi

#######################################
# 6) Start Apache (IMPORTANT)
#######################################
echo "🚀 Starting Apache"
exec docker-entrypoint.sh "$@"
