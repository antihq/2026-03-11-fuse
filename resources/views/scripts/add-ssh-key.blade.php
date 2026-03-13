#!/bin/bash

# SSH Key Setup Script for Server Provisioning
# This script adds user's SSH public key to root access

set -e

echo "Adding SSH public key to root..."

mkdir -p /root/.ssh
chmod 700 /root/.ssh

cat <<'SSHKEY' >> /root/.ssh/authorized_keys
{{ $sshPublicKey }}
SSHKEY

chmod 600 /root/.ssh/authorized_keys

echo "SSH key added successfully."

# Notify system that SSH key is ready for provisioning
curl -s "{!! $callbackUrl !!}" > /dev/null 2>&1 || true
