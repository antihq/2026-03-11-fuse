<?php

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

it('runs task successfully', function () {
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result(output: 'mkdir output', exitCode: 0))
            ->push(Process::result(output: 'scp output', exitCode: 0))
            ->push(Process::result(output: "Connected to test-server\nLinux test-server", exitCode: 0)),
    ]);

    $task = Task::create([
        'user_id' => $this->user->id,
        'server_id' => $this->server->id,
        'ssh_user' => 'root',
        'script' => 'echo "Connected to $(hostname)" && uname -a',
        'timeout' => 10,
    ]);

    $task->run();

    expect($task->status)->toBe('finished');
    expect($task->exit_code)->toBe(0);
    expect($task->output)->toContain('Connected to test-server');
    expect($task->finished_at)->not->toBeNull();
});

it('handles task failure', function () {
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result(output: 'mkdir output', exitCode: 0))
            ->push(Process::result(output: 'scp output', exitCode: 0))
            ->push(Process::result(output: 'Permission denied', exitCode: 255)),
    ]);

    $task = Task::create([
        'user_id' => $this->user->id,
        'server_id' => $this->server->id,
        'ssh_user' => 'root',
        'script' => 'exit 1',
        'timeout' => 10,
    ]);

    $task->run();

    expect($task->status)->toBe('finished');
    expect($task->exit_code)->toBe(255);
    expect($task->output)->toContain('Permission denied');
});

it('marks task as running when executed', function () {
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result(output: 'mkdir output', exitCode: 0))
            ->push(Process::result(output: 'scp output', exitCode: 0))
            ->push(Process::result(output: 'success', exitCode: 0)),
    ]);

    $task = Task::create([
        'user_id' => $this->user->id,
        'server_id' => $this->server->id,
        'ssh_user' => 'root',
        'script' => 'echo test',
        'timeout' => 10,
    ]);

    $task->refresh();

    expect($task->status)->toBe('pending');
    expect($task->started_at)->toBeNull();

    $task->run();

    expect($task->status)->toBe('finished');
    expect($task->started_at)->not->toBeNull();
});

it('belongs to a user and server', function () {
    $task = Task::factory()->create([
        'user_id' => $this->user->id,
        'server_id' => $this->server->id,
    ]);

    expect($task->user->id)->toBe($this->user->id);
    expect($task->server->id)->toBe($this->server->id);
});

it('can check if task was successful', function () {
    $task = Task::factory()->finished()->create([
        'user_id' => $this->user->id,
        'server_id' => $this->server->id,
    ]);

    expect($task->successful())->toBeTrue();

    $failedTask = Task::factory()->failed()->create([
        'user_id' => $this->user->id,
        'server_id' => $this->server->id,
    ]);

    expect($failedTask->successful())->toBeFalse();
});

it('can mark task as timed out', function () {
    $task = Task::factory()->create([
        'user_id' => $this->user->id,
        'server_id' => $this->server->id,
        'status' => 'running',
    ]);

    $result = $task->markAsTimedOut();

    expect($task->status)->toBe('timeout');
    expect($task->finished_at)->not->toBeNull();
    expect($result->id)->toBe($task->id);
});

it('runs task with non-root user', function () {
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result(output: 'mkdir output', exitCode: 0))
            ->push(Process::result(output: 'scp output', exitCode: 0))
            ->push(Process::result(output: 'Connected as deploy user', exitCode: 0)),
    ]);

    $task = Task::create([
        'user_id' => $this->user->id,
        'server_id' => $this->server->id,
        'ssh_user' => 'deploy',
        'script' => 'whoami',
        'timeout' => 10,
    ]);

    $task->run();

    expect($task->status)->toBe('finished');
    expect($task->exit_code)->toBe(0);
    expect($task->output)->toContain('deploy');
});
