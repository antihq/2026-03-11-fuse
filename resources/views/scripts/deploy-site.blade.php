#!/bin/bash
set -e

SITES_USER="{{ $sitesUser }}"
HOSTNAME="{{ $hostname }}"
REPOSITORY_URL="{{ $repositoryUrl }}"
REPOSITORY_BRANCH="{{ $repositoryBranch }}"
PHP_VERSION="{{ $phpVersion }}"

SITE_DIR="/home/$SITES_USER/$HOSTNAME"
REPO_DIR="$SITE_DIR/repository"

echo "=== Starting deployment for $HOSTNAME ==="

cd "$SITE_DIR"

@if(isset($hookBeforeUpdatingRepository) && $hookBeforeUpdatingRepository)
echo "Running hook before updating repository..."
cd "$REPO_DIR"
{!! $hookBeforeUpdatingRepository !!}
@endif

if [ -d "$REPO_DIR/.git" ]; then
    echo "Updating existing repository..."
    cd "$REPO_DIR"
    git pull origin $REPOSITORY_BRANCH
else
    echo "Cloning repository..."
    rm -rf "$REPO_DIR"
    git clone --branch $REPOSITORY_BRANCH $REPOSITORY_URL "$REPO_DIR"
    cd "$REPO_DIR"
fi

@if(isset($hookAfterUpdatingRepository) && $hookAfterUpdatingRepository)
echo "Running hook after updating repository..."
cd "$REPO_DIR"
{!! $hookAfterUpdatingRepository !!}
@endif

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
