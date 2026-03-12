#!/bin/bash
set -e

SITES_USER="{{ $sitesUser }}"
HOSTNAME="{{ $hostname }}"
PHP_VERSION="{{ $phpVersion }}"
SITE_DIR="/home/$SITES_USER/$HOSTNAME"

echo "Creating site directory structure..."
mkdir -p "$SITE_DIR/repository"

echo "Generating Caddyfile..."

cat > "$SITE_DIR/Caddyfile" << 'CADDYFILE'
{{ $hostname }} {
    root * /home/{{ $sitesUser }}/{{ $hostname }}/repository/public

    php_fastcgi unix//run/php/php{{ $phpVersion }}-fpm.sock
    file_server

    encode gzip zstd

    header {
        X-Frame-Options "SAMEORIGIN"
        X-Content-Type-Options "nosniff"
        X-XSS-Protection "1; mode=block"
        Referrer-Policy "strict-origin-when-cross-origin"
    }

    log {
        output file /home/{{ $sitesUser }}/{{ $hostname }}/access.log
        format json
    }
}
CADDYFILE

echo "Setting permissions..."
chown -R $SITES_USER:$SITES_USER "$SITE_DIR"
chmod 755 "$SITE_DIR"

echo "Site Caddyfile created successfully!"
