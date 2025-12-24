#!/bin/bash
set -e

echo "Waiting for database..."
until mariadb -h"${WORDPRESS_DB_HOST}" -u"${WORDPRESS_DB_USER}" -p"${WORDPRESS_DB_PASSWORD}" -e "SELECT 1" >/dev/null 2>&1; do
  sleep 2
done
echo "DB is up."

cd /var/www/html

# WordPress core موجود بالفعل في الإيميج
if [ ! -f wp-config.php ]; then
  echo "Creating wp-config.php..."

  wp config create \
    --dbname="${WORDPRESS_DB_NAME}" \
    --dbuser="${WORDPRESS_DB_USER}" \
    --dbpass="${WORDPRESS_DB_PASSWORD}" \
    --dbhost="${WORDPRESS_DB_HOST}" \
    --skip-check \
    --allow-root

  wp config set DISALLOW_FILE_EDIT true --raw --allow-root
  wp config set DISALLOW_FILE_MODS true --raw --allow-root
  wp config set AUTOMATIC_UPDATER_DISABLED true --raw --allow-root
fi

if ! wp core is-installed --allow-root; then
  echo "Installing WordPress..."

  wp core install \
    --url="${WP_URL}" \
    --title="${WP_TITLE}" \
    --admin_user="${WP_ADMIN_USER}" \
    --admin_password="${WP_ADMIN_PASS}" \
    --admin_email="${WP_ADMIN_EMAIL}" \
    --skip-email \
    --allow-root

  wp theme activate "${COONEX_THEME_SLUG}" --allow-root || true

  for p in ${COONEX_PLUGINS}; do
    wp plugin activate "$p" --allow-root || true
  done
else
  echo "WordPress already installed. Skipping install."
fi

exec docker-entrypoint.sh "$@"
