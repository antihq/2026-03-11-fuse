<?php

use App\Jobs\InstallSiteQueue;
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

test('handle creates task and installs queue supervisor config', function () {
    Process::fake([
        '*' => Process::result(exitCode: 0),
    ]);

    $job = new InstallSiteQueue($this->site->id);
    $job->handle();

    $tasks = Task::where('server_id', $this->server->id)->get();

    expect($tasks)->toHaveCount(2);
    expect($tasks[0])
        ->user_id->toBe($this->user->id)
        ->ssh_user->toBe('root');
});

test('handle creates log directory with correct permissions', function () {
    Process::fake([
        '*' => Process::result(exitCode: 0),
    ]);

    $job = new InstallSiteQueue($this->site->id);
    $job->handle();

    $tasks = Task::where('server_id', $this->server->id)->get();
    $reloadTask = $tasks->first(fn ($t) => str_contains($t->script, 'mkdir -p'));

    expect($reloadTask->script)
        ->toContain('mkdir -p /home/deploy/example.com/storage/logs')
        ->toContain('chown deploy:deploy /home/deploy/example.com/storage/logs')
        ->toContain('chmod 775 /home/deploy/example.com/storage/logs');
});

test('handle reloads supervisor and starts queue workers', function () {
    Process::fake([
        '*' => Process::result(exitCode: 0),
    ]);

    $job = new InstallSiteQueue($this->site->id);
    $job->handle();

    $tasks = Task::where('server_id', $this->server->id)->get();
    $reloadTask = $tasks->sortBy('id')->last();

    expect($reloadTask->script)
        ->toContain('supervisorctl reread')
        ->toContain('supervisorctl update')
        ->toContain('supervisorctl start site-'.$this->site->id.':*');
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

    $job = new InstallSiteQueue($site->id);
    $job->handle();

    expect(Task::where('server_id', $server->id)->count())->toBe(0);
});

test('failed method sets queue_enabled to false', function () {
    $this->site->update(['queue_enabled' => true]);

    $job = new InstallSiteQueue($this->site->id);
    $job->failed(new Exception('Test failure'));

    expect($this->site->fresh()->queue_enabled)->toBeFalse();
});

test('supervisor config contains correct php version', function () {
    $config = view('scripts.site-queue-supervisor', [
        'site' => $this->site,
        'sitesUser' => 'deploy',
        'repoPath' => '/home/deploy/example.com/repository',
        'logPath' => '/home/deploy/example.com/storage/logs',
    ])->render();

    expect($config)
        ->toContain('php8.4 /home/deploy/example.com/repository/artisan queue:work')
        ->toContain('numprocs=3');
});

test('supervisor config contains correct paths', function () {
    $config = view('scripts.site-queue-supervisor', [
        'site' => $this->site,
        'sitesUser' => 'deploy',
        'repoPath' => '/home/deploy/example.com/repository',
        'logPath' => '/home/deploy/example.com/storage/logs',
    ])->render();

    expect($config)
        ->toContain('program:site-'.$this->site->id)
        ->toContain('directory=/home/deploy/example.com/repository')
        ->toContain('stdout_logfile=/home/deploy/example.com/storage/logs/queue.log')
        ->toContain('stderr_logfile=/home/deploy/example.com/storage/logs/queue-error.log')
        ->toContain('user=deploy');
});
