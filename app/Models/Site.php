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
        'database_name',
        'database_user',
        'database_password',
        'database_created_at',
        'queue_enabled',
        'queue_processes',
        'nightwatch_enabled',
        'scheduler_enabled',
        'horizon_enabled',
    ];

    protected function casts(): array
    {
        return [
            'server_id' => 'integer',
            'configured_at' => 'datetime',
            'deployed_at' => 'datetime',
            'database_password' => 'encrypted',
            'database_created_at' => 'datetime',
            'queue_enabled' => 'boolean',
            'queue_processes' => 'integer',
            'nightwatch_enabled' => 'boolean',
            'scheduler_enabled' => 'boolean',
            'horizon_enabled' => 'boolean',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function envPath(): string
    {
        return "/home/{$this->server->sites_user}/{$this->hostname}/repository/.env";
    }

    public function caddyfilePath(): string
    {
        return "/home/{$this->server->sites_user}/{$this->hostname}/Caddyfile";
    }

    public function supervisorConfigPath(): string
    {
        return "/etc/supervisor/conf.d/site-{$this->id}.conf";
    }

    public function queueLogPath(): string
    {
        return "/home/{$this->server->sites_user}/{$this->hostname}/storage/logs";
    }

    public function nightwatchSupervisorConfigPath(): string
    {
        return "/etc/supervisor/conf.d/site-{$this->id}-nightwatch.conf";
    }

    public function nightwatchLogPath(): string
    {
        return "/home/{$this->server->sites_user}/{$this->hostname}/storage/logs";
    }

    public function schedulerCronPath(): string
    {
        return "/etc/cron.d/site-{$this->id}-scheduler";
    }

    public function horizonSupervisorConfigPath(): string
    {
        return "/etc/supervisor/conf.d/site-{$this->id}-horizon.conf";
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

    if [ -n "\$DB_DATABASE" ]; then
        echo "Updating database credentials..."
        sed -i "s|^DB_DATABASE=.*|DB_DATABASE=\$DB_DATABASE|g" .env
        sed -i "s|^DB_USERNAME=.*|DB_USERNAME=\$DB_USERNAME|g" .env
        sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=\$DB_PASSWORD|g" .env
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
