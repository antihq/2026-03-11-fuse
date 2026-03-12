<?php

use App\Jobs\FinishTask;
use App\Models\Server;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\URL;

beforeEach(function () {
    $this->user = User::factory()->withSshKeys()->create();
    $this->server = Server::factory()->for($this->user)->create([
        'ip_address' => '192.168.1.1',
    ]);
});

test('callback dispatches finish task job for running task', function () {
    $task = Task::create([
        'user_id' => $this->user->id,
        'server_id' => $this->server->id,
        'ssh_user' => 'root',
        'script' => 'echo test',
        'status' => 'running',
        'timeout' => 60,
    ]);

    Bus::fake();

    $url = URL::signedRoute('api.callback', ['task' => $task->id]);

    $response = $this->postJson($url, [
        'exit_code' => 0,
    ]);

    $response->assertNoContent();

    Bus::assertDispatched(FinishTask::class, function ($job) use ($task) {
        return $job->task->id === $task->id && $job->exitCode === 0;
    });
});

test('callback returns 404 for non-running task', function () {
    $task = Task::create([
        'user_id' => $this->user->id,
        'server_id' => $this->server->id,
        'ssh_user' => 'root',
        'script' => 'echo test',
        'status' => 'finished',
        'timeout' => 60,
    ]);

    $url = URL::signedRoute('api.callback', ['task' => $task->id]);

    $response = $this->postJson($url, [
        'exit_code' => 0,
    ]);

    $response->assertNotFound();
});

test('callback defaults exit code to 1 when not provided', function () {
    $task = Task::create([
        'user_id' => $this->user->id,
        'server_id' => $this->server->id,
        'ssh_user' => 'root',
        'script' => 'echo test',
        'status' => 'running',
        'timeout' => 60,
    ]);

    Bus::fake();

    $url = URL::signedRoute('api.callback', ['task' => $task->id]);

    $response = $this->postJson($url);

    $response->assertNoContent();

    Bus::assertDispatched(FinishTask::class, function ($job) {
        return $job->exitCode === 1;
    });
});

test('callback returns 403 for unsigned url', function () {
    $task = Task::create([
        'user_id' => $this->user->id,
        'server_id' => $this->server->id,
        'ssh_user' => 'root',
        'script' => 'echo test',
        'status' => 'running',
        'timeout' => 60,
    ]);

    $response = $this->postJson("/api/callback/{$task->id}", [
        'exit_code' => 0,
    ]);

    $response->assertForbidden();
});
