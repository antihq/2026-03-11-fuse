#!/bin/bash
set -e

SITES_USER="{{ $sitesUser }}"
HOSTNAME="{{ $hostname }}"
REPOSITORY_URL="{{ $repositoryUrl }}"
REPOSITORY_BRANCH="{{ $repositoryBranch }}"
PHP_VERSION="{{ $phpVersion }}"
PHP_BINARY="php{{ $phpVersion }}"

SITE_DIR="/home/$SITES_USER/$HOSTNAME"
REPO_DIR="$SITE_DIR/repository"
PUBLIC_DIR="$SITE_DIR/public"

echo "=== Starting deployment for $HOSTNAME ==="

cd "$SITE_DIR"

# Clone or update repository
if [ -d "$REPO_DIR/.git" ]; then
    echo "Updating existing repository..."
    cd "$REPO_DIR"
    git fetch origin
    git reset --hard origin/$REPOSITORY_BRANCH
else
    echo "Cloning repository..."
    rm -rf "$REPO_DIR"
    git clone --branch $REPOSITORY_BRANCH $REPOSITORY_URL "$REPO_DIR"
    cd "$REPO_DIR"
fi

echo "Installing Composer dependencies..."
$PHP_BINARY $(which composer) install --no-dev --no-interaction --prefer-dist --optimize-autoloader

if [ -f "package.json" ]; then
    echo "Installing NPM dependencies..."
    npm install --prefer-offline --no-audit

    echo "Building assets..."
    npm run build
fi

# Laravel-specific setup
if [ -f "artisan" ]; then
    echo "Setting up Laravel application..."

    # Create .env from .env.example if not exists
    if [ ! -f ".env" ] && [ -f ".env.example" ]; then
        cp .env.example .env
        $PHP_BINARY artisan key:generate --force
    fi

    # Run migrations (optional, uncomment if needed)
    # $PHP_BINARY artisan migrate --force

    # Cache optimizations
    $PHP_BINARY artisan config:cache
    $PHP_BINARY artisan route:cache
    $PHP_BINARY artisan view:cache
    $PHP_BINARY artisan event:cache

    # Storage link
    $PHP_BINARY artisan storage:link
fi

# Update public directory symlink or copy
echo "Updating public directory..."
rm -rf "$PUBLIC_DIR"/*

# Check for custom web folder (Laravel uses 'public')
if [ -d "$REPO_DIR/public" ]; then
    # Create symlinks for all files in public except maintenance.html
    cd "$REPO_DIR/public"
    for item in *; do
        if [ "$item" != "maintenance.html" ]; then
            ln -s "$REPO_DIR/public/$item" "$PUBLIC_DIR/$item"
        fi
    done
else
    # For static sites or custom structures
    cp -r "$REPO_DIR/"* "$PUBLIC_DIR/"
fi

# Remove maintenance page to go live
echo "Removing maintenance page..."
rm -f "$PUBLIC_DIR/maintenance.html"

# Set permissions
echo "Setting permissions..."
chown -R $SITES_USER:$SITES_USER "$SITE_DIR"
chmod -R 755 "$SITE_DIR"

if [ -d "$REPO_DIR/storage" ]; then
    chmod -R 775 "$REPO_DIR/storage"
fi

if [ -d "$REPO_DIR/bootstrap/cache" ]; then
    chmod -R 775 "$REPO_DIR/bootstrap/cache"
fi

echo "=== Deployment complete! ==="
