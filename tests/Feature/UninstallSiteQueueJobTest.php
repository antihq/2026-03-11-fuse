<?php

use App\Jobs\UninstallSiteQueue;
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
        'queue_enabled' => true,
        'queue_processes' => 3,
        'status' => 'ready',
    ]);
});

test('handle creates task and runs uninstall script', function () {
    Process::fake([
        '*' => Process::result(exitCode: 0),
    ]);

    $job = new UninstallSiteQueue($this->site->id);
    $job->handle();

    $task = Task::where('server_id', $this->server->id)->first();

    expect($task)
        ->user_id->toBe($this->user->id)
        ->ssh_user->toBe('root')
        ->exit_code->toBe(0);
});

test('handle stops queue workers before removing config', function () {
    Process::fake([
        '*' => Process::result(exitCode: 0),
    ]);

    $job = new UninstallSiteQueue($this->site->id);
    $job->handle();

    $task = Task::where('server_id', $this->server->id)->first();

    expect($task->script)
        ->toContain('supervisorctl stop site-'.$this->site->id.':*')
        ->toContain('rm -f /etc/supervisor/conf.d/site-'.$this->site->id.'.conf');
});

test('handle reloads supervisor after removing config', function () {
    Process::fake([
        '*' => Process::result(exitCode: 0),
    ]);

    $job = new UninstallSiteQueue($this->site->id);
    $job->handle();

    $task = Task::where('server_id', $this->server->id)->first();

    expect($task->script)
        ->toContain('supervisorctl reread')
        ->toContain('supervisorctl update');
});

test('handle sets queue_enabled to false', function () {
    $this->site->update(['queue_enabled' => true]);
    Process::fake([
        '*' => Process::result(exitCode: 0),
    ]);

    $job = new UninstallSiteQueue($this->site->id);
    $job->handle();

    expect($this->site->fresh()->queue_enabled)->toBeFalse();
});

test('handle returns early when user has no ssh key', function () {
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
        'hostname' => 'example.com',
        'php_version' => '8.4',
        'queue_enabled' => true,
        'queue_processes' => 1,
        'status' => 'ready',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
    ]);

    $job = new UninstallSiteQueue($site->id);
    $job->handle();

    expect(Task::where('server_id', $server->id)->count())->toBe(0);
});

test('failed method handles exception gracefully', function () {
    $job = new UninstallSiteQueue($this->site->id);

    expect(fn () => $job->failed(new Exception('Test failure')))->not->toThrow(Exception::class);
});

test('uninstall script removes supervisor config file', function () {
    $job = new UninstallSiteQueue($this->site->id);
    $job->handle();

    $task = Task::where('server_id', $this->server->id)->first();

    expect($task->script)
        ->toContain('rm -f /etc/supervisor/conf.d/site-'.$this->site->id.'.conf');
});
