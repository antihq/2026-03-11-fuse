#!/bin/bash
set -e

SITES_USER="{{ $sitesUser }}"
HOSTNAME="{{ $hostname }}"

echo "Registering site with Caddy..."
if ! grep -q "import /home/$SITES_USER/$HOSTNAME/Caddyfile" /etc/caddy/Sites.caddy; then
    echo "import /home/$SITES_USER/$HOSTNAME/Caddyfile" >> /etc/caddy/Sites.caddy
fi

echo "Reloading Caddy..."
service caddy reload

echo "Caddy configuration updated successfully!"
