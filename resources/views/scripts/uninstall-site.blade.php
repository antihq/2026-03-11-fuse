#!/bin/bash
set -e

SITES_USER="{{ $sitesUser }}"
HOSTNAME="{{ $hostname }}"
SITE_DIR="/home/$SITES_USER/$HOSTNAME"
DATABASE_NAME="{{ $databaseName }}"
DATABASE_USER="{{ $databaseUser }}"
MYSQL_ROOT_PASSWORD="{{ $mysqlRootPassword }}"

echo "=== Uninstalling site $HOSTNAME ==="

echo "Removing Caddy import..."
sed -i "\|import /home/$SITES_USER/$HOSTNAME/Caddyfile|d" /etc/caddy/Sites.caddy

echo "Reloading Caddy..."
service caddy reload

echo "Deleting site directory..."
rm -rf "$SITE_DIR"

@isset($databaseName)
echo "Dropping database: $DATABASE_NAME"
mysql --user="root" --password="$MYSQL_ROOT_PASSWORD" -e "DROP DATABASE IF EXISTS \`$DATABASE_NAME\`;"

echo "Dropping user: $DATABASE_USER"
mysql --user="root" --password="$MYSQL_ROOT_PASSWORD" -e "DROP USER IF EXISTS \`$DATABASE_USER\`@'%';"

echo "Flushing privileges"
mysql --user="root" --password="$MYSQL_ROOT_PASSWORD" -e "FLUSH PRIVILEGES;"
@endisset

echo "=== Site uninstalled successfully! ==="
