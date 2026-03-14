<?php

use App\Jobs\ConfigureSite;
use App\Models\Server;
use App\Models\Site;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->user = User::factory()->withSshKeys()->create();
    $this->server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'provisioned_at' => now(),
    ]);
    $this->site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'example.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
    ]);
});

test('handle creates two tasks and updates site to active on success', function () {
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result(output: 'mkdir output', exitCode: 0))
            ->push(Process::result(output: 'scp output', exitCode: 0))
            ->push(Process::result(output: 'Site Caddyfile created', exitCode: 0))
            ->push(Process::result(output: 'mkdir output', exitCode: 0))
            ->push(Process::result(output: 'scp output', exitCode: 0))
            ->push(Process::result(output: 'Caddy configuration updated', exitCode: 0)),
    ]);

    $job = new ConfigureSite($this->site->id);
    $job->handle();

    expect($this->site->fresh())
        ->status->toBe('ready')
        ->configured_at->not->toBeNull();

    $tasks = Task::where('server_id', $this->server->id)->orderBy('id')->get();
    expect($tasks)->toHaveCount(2)
        ->and($tasks[0])->ssh_user->toBe('deploy')
        ->and($tasks[0])->exit_code->toBe(0)
        ->and($tasks[1])->ssh_user->toBe('root')
        ->and($tasks[1])->exit_code->toBe(0);
});

test('task 1 failure prevents task 2 from running', function () {
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result(output: 'mkdir output', exitCode: 0))
            ->push(Process::result(output: 'scp output', exitCode: 0))
            ->push(Process::result(output: 'Permission denied', exitCode: 1)),
    ]);

    $job = new ConfigureSite($this->site->id);
    $job->handle();

    expect($this->site->fresh())
        ->status->toBe('failed')
        ->configured_at->toBeNull();

    expect(Task::where('server_id', $this->server->id)->count())->toBe(1);
});

test('task 2 failure marks site as failed', function () {
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result(output: 'mkdir output', exitCode: 0))
            ->push(Process::result(output: 'scp output', exitCode: 0))
            ->push(Process::result(output: 'Site Caddyfile created', exitCode: 0))
            ->push(Process::result(output: 'mkdir output', exitCode: 0))
            ->push(Process::result(output: 'scp output', exitCode: 0))
            ->push(Process::result(output: 'Permission denied', exitCode: 1)),
    ]);

    $job = new ConfigureSite($this->site->id);
    $job->handle();

    expect($this->site->fresh())
        ->status->toBe('failed')
        ->configured_at->toBeNull();

    $tasks = Task::where('server_id', $this->server->id)->orderBy('id')->get();
    expect($tasks)->toHaveCount(2)
        ->and($tasks[0])->exit_code->toBe(0)
        ->and($tasks[1])->exit_code->toBe(1);
});

test('handle updates site to failed when user has no ssh key', function () {
    $user = User::factory()->create();
    $server = Server::create([
        'user_id' => $user->id,
        'name' => 'No Key Server',
        'ip_address' => '10.0.0.1',
        'ram_mb' => 1024,
        'sites_user' => 'deploy',
        'provisioned_at' => now(),
    ]);
    $site = Site::create([
        'server_id' => $server->id,
        'hostname' => 'nokey.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
    ]);

    $job = new ConfigureSite($site->id);
    $job->handle();

    expect($site->fresh())
        ->status->toBe('failed')
        ->configured_at->toBeNull();

    expect(Task::where('server_id', $server->id)->count())->toBe(0);
});

test('site caddyfile script contains expected content', function () {
    $script = view('scripts.site-caddyfile', [
        'hostname' => 'example.com',
        'phpVersion' => '8.4',
        'sitesUser' => 'deploy',
    ])->render();

    expect($script)
        ->toContain('SITES_USER="deploy"')
        ->toContain('HOSTNAME="example.com"')
        ->toContain('PHP_VERSION="8.4"')
        ->toContain('root * /home/deploy/example.com/repository/public')
        ->toContain('php8.4-fpm.sock')
        ->toContain('repository')
        ->not->toContain('service caddy reload')
        ->not->toContain('/etc/caddy/Sites.caddy');
});

test('update caddy imports script contains expected content', function () {
    $script = view('scripts.update-caddy-imports', [
        'hostname' => 'example.com',
        'sitesUser' => 'deploy',
    ])->render();

    expect($script)
        ->toContain('SITES_USER="deploy"')
        ->toContain('HOSTNAME="example.com"')
        ->toContain('/etc/caddy/Sites.caddy')
        ->toContain('service caddy reload')
        ->toContain('import /home/$SITES_USER/$HOSTNAME/Caddyfile');
});

test('failed method updates site status', function () {
    $job = new ConfigureSite($this->site->id);
    $job->failed(new Exception('Test failure'));

    expect($this->site->fresh()->status)->toBe('failed');
});

test('site caddyfile script does not create public directory', function () {
    $script = view('scripts.site-caddyfile', [
        'hostname' => 'example.com',
        'phpVersion' => '8.4',
        'sitesUser' => 'deploy',
    ])->render();

    expect($script)
        ->not->toContain('mkdir -p "$SITE_DIR/public"')
        ->toContain('mkdir -p "$SITE_DIR/repository"');
});

test('site caddyfile script does not create maintenance page', function () {
    $script = view('scripts.site-caddyfile', [
        'hostname' => 'example.com',
        'phpVersion' => '8.4',
        'sitesUser' => 'deploy',
    ])->render();

    expect($script)
        ->not->toContain('maintenance.html')
        ->not->toContain('@maintenance');
});

test('validate caddyfile script contains expected content', function () {
    $script = view('scripts.validate-caddyfile', [
        'caddyfilePath' => '/home/deploy/example.com/Caddyfile',
    ])->render();

    expect($script)
        ->toContain('caddy validate')
        ->toContain('--config "$CADDYFILE_PATH"')
        ->toContain('--adapter caddyfile')
        ->toContain('CADDYFILE_PATH="/home/deploy/example.com/Caddyfile"');
});
