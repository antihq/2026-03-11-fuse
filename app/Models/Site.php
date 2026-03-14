<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'server_id',
        'hostname',
        'php_version',
        'size',
        'repository_url',
        'repository_branch',
        'hook_before_updating_repository',
        'hook_after_updating_repository',
        'status',
        'configured_at',
        'deployed_at',
    ];

    protected function casts(): array
    {
        return [
            'server_id' => 'integer',
            'configured_at' => 'datetime',
            'deployed_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public static function defaultAfterHook(string $phpVersion): string
    {
        return <<<BASH
echo "Installing Composer dependencies..."
php{$phpVersion} $(which composer) install --no-dev --no-interaction --prefer-dist --optimize-autoloader

if [ -f "package.json" ]; then
    echo "Installing NPM dependencies..."
    npm install --prefer-offline --no-audit

    echo "Building assets..."
    npm run build
fi

if [ -f "artisan" ]; then
    echo "Setting up Laravel application..."

    if [ ! -f ".env" ] && [ -f ".env.example" ]; then
        cp .env.example .env
        php{$phpVersion} artisan key:generate --force
        if [ -n "\$HOSTNAME" ]; then
            sed -i "s|^APP_URL=.*|APP_URL=https://\$HOSTNAME|g" .env
        fi
    fi

    php{$phpVersion} artisan config:cache
    php{$phpVersion} artisan route:cache
    php{$phpVersion} artisan view:cache
    php{$phpVersion} artisan event:cache

    php{$phpVersion} artisan storage:link
fi
BASH;
    }
}
