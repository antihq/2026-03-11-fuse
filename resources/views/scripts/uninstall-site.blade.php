#!/bin/bash
set -e

SITES_USER="{{ $sitesUser }}"
HOSTNAME="{{ $hostname }}"
SITE_DIR="/home/$SITES_USER/$HOSTNAME"

echo "=== Uninstalling site $HOSTNAME ==="

echo "Removing Caddy import..."
sed -i "\|import /home/$SITES_USER/$HOSTNAME/Caddyfile|d" /etc/caddy/Sites.caddy

echo "Deleting site directory..."
rm -rf "$SITE_DIR"

echo "Reloading Caddy..."
service caddy reload

echo "=== Site uninstalled successfully! ==="
