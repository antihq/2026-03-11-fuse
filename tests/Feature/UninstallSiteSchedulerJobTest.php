<?php

use App\Jobs\UninstallSiteScheduler;
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
        'scheduler_enabled' => true,
        'status' => 'ready',
    ]);
});

test('handle creates task and runs uninstall script', function () {
    Process::fake([
        '*' => Process::result(exitCode: 0),
    ]);

    $job = new UninstallSiteScheduler($this->site->id);
    $job->handle();

    $task = Task::where('server_id', $this->server->id)->first();

    expect($task)
        ->user_id->toBe($this->user->id)
        ->ssh_user->toBe('root')
        ->exit_code->toBe(0);
});

test('handle removes cron file', function () {
    Process::fake([
        '*' => Process::result(exitCode: 0),
    ]);

    $job = new UninstallSiteScheduler($this->site->id);
    $job->handle();

    $task = Task::where('server_id', $this->server->id)->first();

    expect($task->script)
        ->toContain('rm -f /etc/cron.d/site-'.$this->site->id.'-scheduler');
});

test('handle sets scheduler_enabled to false', function () {
    $this->site->update(['scheduler_enabled' => true]);
    Process::fake([
        '*' => Process::result(exitCode: 0),
    ]);

    $job = new UninstallSiteScheduler($this->site->id);
    $job->handle();

    expect($this->site->fresh()->scheduler_enabled)->toBeFalse();
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
        'scheduler_enabled' => true,
        'status' => 'ready',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
    ]);

    $job = new UninstallSiteScheduler($site->id);
    $job->handle();

    expect(Task::where('server_id', $server->id)->count())->toBe(0);
});

test('failed method handles exception gracefully', function () {
    $job = new UninstallSiteScheduler($this->site->id);

    expect(fn () => $job->failed(new Exception('Test failure')))->not->toThrow(Exception::class);
});

test('uninstall script removes cron file', function () {
    $job = new UninstallSiteScheduler($this->site->id);
    $job->handle();

    $task = Task::where('server_id', $this->server->id)->first();

    expect($task->script)
        ->toContain('rm -f /etc/cron.d/site-'.$this->site->id.'-scheduler');
});
