<?php

use App\Jobs\FinishTask;
use App\Models\Server;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->user = User::factory()->withSshKeys()->create();
    $this->server = Server::factory()->for($this->user)->create([
        'ip_address' => '192.168.1.1',
    ]);
});

test('handle calls task finish method with exit code', function () {
    Process::fake();

    $task = Task::create([
        'user_id' => $this->user->id,
        'server_id' => $this->server->id,
        'ssh_user' => 'root',
        'script' => 'echo test',
        'status' => 'running',
        'timeout' => 10,
    ]);

    $job = new FinishTask($task, 0);
    $job->handle();

    expect($task->fresh()->status)->toBe('finished');
    expect($task->exit_code)->toBe(0);
});

test('handle with exit code zero marks task successful', function () {
    Process::fake();

    $task = Task::create([
        'user_id' => $this->user->id,
        'server_id' => $this->server->id,
        'ssh_user' => 'root',
        'script' => 'echo test',
        'status' => 'running',
        'timeout' => 10,
    ]);

    $job = new FinishTask($task, 0);
    $job->handle();

    expect($task->fresh()->status)->toBe('finished');
    expect($task->exit_code)->toBe(0);
    expect($task->successful())->toBeTrue();
});

test('handle with non-zero exit code marks task failed', function () {
    Process::fake();

    $task = Task::create([
        'user_id' => $this->user->id,
        'server_id' => $this->server->id,
        'ssh_user' => 'root',
        'script' => 'echo test',
        'status' => 'running',
        'timeout' => 10,
    ]);

    $job = new FinishTask($task, 1);
    $job->handle();

    expect($task->fresh()->status)->toBe('finished');
    expect($task->exit_code)->toBe(1);
    expect($task->successful())->toBeFalse();
});
