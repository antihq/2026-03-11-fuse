#!/bin/bash
set -e

CADDYFILE_PATH="{{ $caddyfilePath }}"

echo "Validating Caddyfile..."
caddy validate --config "$CADDYFILE_PATH" --adapter caddyfile

echo "Caddyfile is valid!"
