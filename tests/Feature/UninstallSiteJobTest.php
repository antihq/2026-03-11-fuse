<?php

use App\Jobs\UninstallSite;
use App\Models\Server;
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
});

test('handle creates task and runs uninstall script', function () {
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result(output: 'mkdir output', exitCode: 0))
            ->push(Process::result(output: 'scp output', exitCode: 0))
            ->push(Process::result(output: 'Site uninstalled successfully', exitCode: 0)),
    ]);

    $job = new UninstallSite($this->server->id, 'example.com');
    $job->handle();

    $task = Task::where('server_id', $this->server->id)->first();

    expect($task)
        ->user_id->toBe($this->user->id)
        ->ssh_user->toBe('root')
        ->exit_code->toBe(0);
});

test('handle does nothing when user has no ssh key', function () {
    $user = User::factory()->create();
    $server = Server::create([
        'user_id' => $user->id,
        'name' => 'No Key Server',
        'ip_address' => '10.0.0.1',
        'ram_mb' => 1024,
        'sites_user' => 'deploy',
        'provisioned_at' => now(),
    ]);

    $job = new UninstallSite($server->id, 'example.com');
    $job->handle();

    expect(Task::where('server_id', $server->id)->count())->toBe(0);
});

test('uninstall script contains expected content', function () {
    $script = view('scripts.uninstall-site', [
        'hostname' => 'example.com',
        'sitesUser' => 'deploy',
    ])->render();

    expect($script)
        ->toContain('SITES_USER="deploy"')
        ->toContain('HOSTNAME="example.com"')
        ->toContain('SITE_DIR="/home/$SITES_USER/$HOSTNAME"')
        ->toContain('sed -i')
        ->toContain('/etc/caddy/Sites.caddy')
        ->toContain('rm -rf "$SITE_DIR"')
        ->toContain('service caddy reload');
});

test('uninstall script removes caddy import line', function () {
    $script = view('scripts.uninstall-site', [
        'hostname' => 'example.com',
        'sitesUser' => 'deploy',
    ])->render();

    expect($script)
        ->toContain('import /home/$SITES_USER/$HOSTNAME/Caddyfile')
        ->toContain('sed -i')
        ->toContain('|d"');
});

test('uninstall script deletes site directory', function () {
    $script = view('scripts.uninstall-site', [
        'hostname' => 'mysite.test',
        'sitesUser' => 'deploy',
    ])->render();

    expect($script)
        ->toContain('SITE_DIR="/home/$SITES_USER/$HOSTNAME"')
        ->toContain('rm -rf "$SITE_DIR"');
});

test('failed method handles exception gracefully', function () {
    $job = new UninstallSite($this->server->id, 'example.com');

    $job->failed(new Exception('Test failure'));

    expect(true)->toBeTrue();
});
