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
        'sites_user' => 'fuse',
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

test('sites are not deleted when server is deleted', function () {
    $site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'example.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
    ]);

    $this->server->delete();

    expect(Site::find($site->id))->not->toBeNull();
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

test('envPath returns correct path for site', function () {
    $site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'example.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
    ]);

    $expectedPath = "/home/{$this->server->sites_user}/example.com/repository/.env";

    expect($site->envPath())->toBe($expectedPath);
});

test('site database fields can be set', function () {
    $site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'example.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'database_name' => 'example_com',
        'database_user' => 'example_com',
        'database_password' => 'secure_password',
    ]);

    expect($site)
        ->database_name->toBe('example_com')
        ->database_user->toBe('example_com')
        ->database_password->toBe('secure_password');
});

test('site database_password is encrypted at rest', function () {
    $site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'example.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'database_password' => 'secure_password',
    ]);

    $rawValue = DB::table('sites')->where('id', $site->id)->value('database_password');

    expect($rawValue)->not->toBe('secure_password')
        ->and($rawValue)->toStartWith('eyJ');
});

test('site database_password can be decrypted and accessed', function () {
    $site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'example.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'database_password' => 'secure_password',
    ]);

    expect($site->database_password)->toBe('secure_password');
});

test('site database_password is visible in array serialization', function () {
    $site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'example.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'database_password' => 'secure_password',
    ]);

    $array = $site->toArray();

    expect($array)->toHaveKey('database_password', 'secure_password');
});

test('site database_password is visible in json serialization', function () {
    $site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'example.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'database_password' => 'secure_password',
    ]);

    $json = $site->toJson();

    expect($json)
        ->toContain('database_password')
        ->toContain('secure_password');
});

test('site database_created_at can be set and is cast to datetime', function () {
    $site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'example.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'database_created_at' => now(),
    ]);

    expect($site->database_created_at)
        ->not->toBeNull()
        ->toBeInstanceOf(DateTimeInterface::class);
});

test('site database_created_at defaults to null', function () {
    $site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'example.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
    ]);

    expect($site->database_created_at)->toBeNull();
});

test('defaultAfterHook includes database credential sed commands', function () {
    $hook = Site::defaultAfterHook('8.4');

    expect($hook)
        ->toContain('if [ -n "$DB_DATABASE" ]; then')
        ->toContain('sed -i "s|^DB_DATABASE=.*|DB_DATABASE=$DB_DATABASE|g" .env')
        ->toContain('sed -i "s|^DB_USERNAME=.*|DB_USERNAME=$DB_USERNAME|g" .env')
        ->toContain('sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=$DB_PASSWORD|g" .env');
});

test('supervisorConfigPath returns correct path', function () {
    $site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'example.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
    ]);

    expect($site->supervisorConfigPath())->toBe('/etc/supervisor/conf.d/site-'.$site->id.'.conf');
});

test('queueLogPath returns correct path', function () {
    $site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'example.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
    ]);

    $expectedPath = "/home/{$this->server->sites_user}/{$site->hostname}/storage/logs";

    expect($site->queueLogPath())->toBe($expectedPath);
});

test('queue_enabled can be set and is cast to boolean', function () {
    $site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'example.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'queue_enabled' => true,
    ]);

    expect($site->queue_enabled)->toBeTrue();
    expect($site->queue_enabled)->toBeBool();
});

test('queue_processes can be set and is cast to integer', function () {
    $site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'example.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'queue_processes' => 5,
    ]);

    expect($site->queue_processes)->toBe(5);
    expect($site->queue_processes)->toBeInt();
});

test('nightwatch_enabled can be set and is cast to boolean', function () {
    $site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'example.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'nightwatch_enabled' => true,
    ]);

    expect($site->nightwatch_enabled)->toBeTrue();
    expect($site->nightwatch_enabled)->toBeBool();
});

test('nightwatchSupervisorConfigPath returns correct path', function () {
    $site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'example.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
    ]);

    expect($site->nightwatchSupervisorConfigPath())->toBe('/etc/supervisor/conf.d/site-'.$site->id.'-nightwatch.conf');
});

test('nightwatchLogPath returns correct path', function () {
    $site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'example.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
    ]);

    $expectedPath = "/home/{$this->server->sites_user}/{$site->hostname}/storage/logs";

    expect($site->nightwatchLogPath())->toBe($expectedPath);
});

test('scheduler_enabled can be set and is cast to boolean', function () {
    $site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'example.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'scheduler_enabled' => true,
    ]);

    expect($site->scheduler_enabled)->toBeTrue();
    expect($site->scheduler_enabled)->toBeBool();
});

test('schedulerCronPath returns correct path', function () {
    $site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'example.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
    ]);

    expect($site->schedulerCronPath())->toBe('/etc/cron.d/site-'.$site->id.'-scheduler');
});

test('horizon_enabled can be set and is cast to boolean', function () {
    $site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'example.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'horizon_enabled' => true,
    ]);

    expect($site->horizon_enabled)->toBeTrue();
    expect($site->horizon_enabled)->toBeBool();
});

test('horizonSupervisorConfigPath returns correct path', function () {
    $site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'example.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
    ]);

    expect($site->horizonSupervisorConfigPath())->toBe('/etc/supervisor/conf.d/site-'.$site->id.'-horizon.conf');
});
