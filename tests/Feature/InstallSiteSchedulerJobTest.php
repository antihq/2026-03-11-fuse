<?php

use App\Jobs\InstallSiteScheduler;
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
        'scheduler_enabled' => false,
        'status' => 'ready',
    ]);
});

test('handle creates task and runs install script', function () {
    Process::fake([
        '*' => Process::result(exitCode: 0),
    ]);

    $job = new InstallSiteScheduler($this->site->id);
    $job->handle();

    $tasks = Task::where('server_id', $this->server->id)->get();

    expect($tasks)->toHaveCount(2);
});

test('handle creates cron file', function () {
    Process::fake([
        '*' => Process::result(exitCode: 0),
    ]);

    $job = new InstallSiteScheduler($this->site->id);
    $job->handle();

    $task = Task::where('server_id', $this->server->id)->first();

    expect($task->script)
        ->toContain('cat > /etc/cron.d/site-'.$this->site->id.'-scheduler')
        ->toContain('* * * * * '.$this->server->sites_user.' php'.$this->site->php_version)
        ->toContain('artisan schedule:run');
});

test('handle creates log directory and file', function () {
    Process::fake([
        '*' => Process::result(exitCode: 0),
    ]);

    $job = new InstallSiteScheduler($this->site->id);
    $job->handle();

    $tasks = Task::where('server_id', $this->server->id)->get();

    expect($tasks[1]->script)
        ->toContain('mkdir -p')
        ->toContain('touch '.$this->site->queueLogPath().'/scheduler.log');
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
        'scheduler_enabled' => false,
        'status' => 'ready',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
    ]);

    $job = new InstallSiteScheduler($site->id);
    $job->handle();

    expect(Task::where('server_id', $server->id)->count())->toBe(0);
});

test('failed method sets scheduler_enabled to false', function () {
    Process::fake([
        '*' => Process::result(exitCode: 1),
    ]);

    $this->site->update(['scheduler_enabled' => true]);

    $job = new InstallSiteScheduler($this->site->id);
    $job->handle();

    $job->failed(new Exception('Test failure'));

    expect($this->site->fresh()->scheduler_enabled)->toBeFalse();
});
