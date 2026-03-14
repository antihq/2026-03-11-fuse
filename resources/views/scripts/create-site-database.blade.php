#!/bin/bash

# Site Database Creation Script
# Database: {{ $databaseName }}
# User: {{ $databaseUser }}

set -e

MYSQL_ROOT_PASSWORD="{{ $mysqlRootPassword }}"
DATABASE_NAME="{{ $databaseName }}"
DATABASE_USER="{{ $databaseUser }}"
DATABASE_PASSWORD="{{ $databasePassword }}"

echo "Creating database: $DATABASE_NAME"
mysql --user="root" --password="$MYSQL_ROOT_PASSWORD" -e "CREATE DATABASE \`$DATABASE_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo "Creating user: $DATABASE_USER"
mysql --user="root" --password="$MYSQL_ROOT_PASSWORD" -e "CREATE USER \`$DATABASE_USER\`@'%' IDENTIFIED BY '$DATABASE_PASSWORD';"

echo "Granting privileges"
mysql --user="root" --password="$MYSQL_ROOT_PASSWORD" -e "GRANT ALL PRIVILEGES ON \`$DATABASE_NAME\`.* TO \`$DATABASE_USER\`@'%';"

echo "Flushing privileges"
mysql --user="root" --password="$MYSQL_ROOT_PASSWORD" -e "FLUSH PRIVILEGES;"

echo "Database creation complete!"
