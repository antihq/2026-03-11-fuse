<?php

use App\Models\Server;
use App\Models\Site;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'provisioned_at' => now(),
    ]);
});

test('site belongs to server', function () {
    $site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'example.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
    ]);

    expect($site->server->id)->toBe($this->server->id);
});

test('server has many sites', function () {
    Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'site1.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo1.git',
        'repository_branch' => 'main',
    ]);

    Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'site2.com',
        'php_version' => '8.3',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo2.git',
        'repository_branch' => 'develop',
    ]);

    expect($this->server->fresh()->sites)->toHaveCount(2);
});

test('sites are deleted when server is deleted', function () {
    $site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'example.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
    ]);

    $this->server->delete();

    expect(Site::find($site->id))->toBeNull();
});

test('site defaults status to pending', function () {
    $site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'pending.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
    ]);

    $site->refresh();

    expect($site->status)->toBe('pending');
});

test('site deployed_at can be set and is cast to datetime', function () {
    $site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'deployed.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'deployed_at' => now(),
    ]);

    expect($site->deployed_at)
        ->not->toBeNull()
        ->toBeInstanceOf(DateTimeInterface::class);
});

test('site deployed_at defaults to null', function () {
    $site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'notdeployed.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
    ]);

    expect($site->deployed_at)->toBeNull();
});

test('site hook_before_updating_repository can be set', function () {
    $site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'hooks.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'hook_before_updating_repository' => 'echo "before"',
    ]);

    expect($site->hook_before_updating_repository)->toBe('echo "before"');
});

test('site hook_after_updating_repository can be set', function () {
    $site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'hooks.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'hook_after_updating_repository' => 'echo "after"',
    ]);

    expect($site->hook_after_updating_repository)->toBe('echo "after"');
});

test('defaultAfterHook returns content with correct PHP version', function () {
    $hook = Site::defaultAfterHook('8.3');

    expect($hook)
        ->toContain('php8.3')
        ->toContain('$(which composer)')
        ->toContain('npm install')
        ->toContain('artisan config:cache');
});

test('defaultAfterHook includes composer install command', function () {
    $hook = Site::defaultAfterHook('8.4');

    expect($hook)
        ->toContain('Installing Composer dependencies')
        ->toContain('install --no-dev --no-interaction --prefer-dist --optimize-autoloader');
});

test('defaultAfterHook includes npm commands', function () {
    $hook = Site::defaultAfterHook('8.4');

    expect($hook)
        ->toContain('Installing NPM dependencies')
        ->toContain('npm install')
        ->toContain('npm run build');
});

test('defaultAfterHook includes Laravel artisan commands', function () {
    $hook = Site::defaultAfterHook('8.4');

    expect($hook)
        ->toContain('Setting up Laravel application')
        ->toContain('artisan key:generate')
        ->toContain('artisan config:cache')
        ->toContain('artisan route:cache')
        ->toContain('artisan view:cache')
        ->toContain('artisan event:cache')
        ->toContain('artisan storage:link');
});

test('defaultAfterHook includes APP_URL configuration command', function () {
    $hook = Site::defaultAfterHook('8.4');

    expect($hook)
        ->toContain('APP_URL=')
        ->toContain('https://');
});

test('defaultAfterHook APP_URL uses HOSTNAME variable', function () {
    $hook = Site::defaultAfterHook('8.4');

    expect($hook)
        ->toContain('$HOSTNAME')
        ->toContain('sed -i');
});

test('defaultAfterHook APP_URL is conditional on HOSTNAME being set', function () {
    $hook = Site::defaultAfterHook('8.4');

    expect($hook)->toContain('if [ -n "$HOSTNAME" ]');
});
