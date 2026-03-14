<?php

use App\Jobs\CreateSiteDatabase;
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
        'mysql_root_password' => 'root_password',
        'provisioned_at' => now(),
    ]);
    $this->site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'example.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'status' => 'configuring',
    ]);
});

test('handle creates task and runs database creation script', function () {
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result(output: 'mkdir output', exitCode: 0))
            ->push(Process::result(output: 'scp output', exitCode: 0))
            ->push(Process::result(output: 'Database creation complete!', exitCode: 0)),
    ]);

    $job = new CreateSiteDatabase($this->site->id);
    $job->handle();

    $task = Task::where('server_id', $this->server->id)->first();

    expect($task)
        ->user_id->toBe($this->user->id)
        ->ssh_user->toBe('root')
        ->exit_code->toBe(0);
});

test('handle updates site with generated database credentials', function () {
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result(output: 'mkdir output', exitCode: 0))
            ->push(Process::result(output: 'scp output', exitCode: 0))
            ->push(Process::result(output: 'Database creation complete!', exitCode: 0)),
    ]);

    $job = new CreateSiteDatabase($this->site->id);
    $job->handle();

    $site = $this->site->fresh();

    expect($site)
        ->database_name->toBe('example_com')
        ->database_user->toBe('example_com')
        ->database_password->not->toBeNull();
});

test('handle sets database_created_at and status to ready on success', function () {
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result(output: 'mkdir output', exitCode: 0))
            ->push(Process::result(output: 'scp output', exitCode: 0))
            ->push(Process::result(output: 'Database creation complete!', exitCode: 0)),
    ]);

    $job = new CreateSiteDatabase($this->site->id);
    $job->handle();

    $site = $this->site->fresh();

    expect($site)
        ->database_created_at->not->toBeNull()
        ->database_created_at->toBeInstanceOf(DateTimeInterface::class)
        ->status->toBe('ready');
});

test('handle sets status to failed on task failure', function () {
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result(output: 'mkdir output', exitCode: 0))
            ->push(Process::result(output: 'scp output', exitCode: 0))
            ->push(Process::result(output: 'Error creating database', exitCode: 1)),
    ]);

    $job = new CreateSiteDatabase($this->site->id);
    $job->handle();

    expect($this->site->fresh())
        ->status->toBe('failed')
        ->database_created_at->toBeNull();
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
        'status' => 'configuring',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
    ]);

    $job = new CreateSiteDatabase($site->id);
    $job->handle();

    expect($site->fresh())
        ->status->toBe('failed')
        ->database_name->toBeNull()
        ->database_user->toBeNull()
        ->database_password->toBeNull();

    expect(Task::where('server_id', $server->id)->count())->toBe(0);
});

test('generateDatabaseName converts dots to underscores', function () {
    $job = new CreateSiteDatabase($this->site->id);

    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('generateDatabaseName');
    $method->setAccessible(true);

    $result = $method->invoke($job, 'example.com');

    expect($result)->toBe('example_com');
});

test('generateDatabaseName converts hyphens to underscores', function () {
    $job = new CreateSiteDatabase($this->site->id);

    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('generateDatabaseName');
    $method->setAccessible(true);

    $result = $method->invoke($job, 'my-site-test.com');

    expect($result)->toBe('my_site_test_com');
});

test('generateDatabaseName handles subdomains', function () {
    $job = new CreateSiteDatabase($this->site->id);

    $reflection = new ReflectionClass($job);
    $method = $reflection->getMethod('generateDatabaseName');
    $method->setAccessible(true);

    $result = $method->invoke($job, 'api.example.com');

    expect($result)->toBe('api_example_com');
});

test('failed method updates site status', function () {
    $job = new CreateSiteDatabase($this->site->id);
    $job->failed(new Exception('Test failure'));

    expect($this->site->fresh()->status)->toBe('failed');
});

test('database creation script contains expected content', function () {
    $script = view('scripts.create-site-database', [
        'databaseName' => 'example_com',
        'databaseUser' => 'example_com',
        'databasePassword' => 'secure_password',
        'mysqlRootPassword' => 'root_pass',
    ])->render();

    expect($script)
        ->toContain('DATABASE_NAME="example_com"')
        ->toContain('DATABASE_USER="example_com"')
        ->toContain('DATABASE_PASSWORD="secure_password"')
        ->toContain('MYSQL_ROOT_PASSWORD="root_pass"')
        ->toContain('CREATE DATABASE')
        ->toContain('CHARACTER SET utf8mb4')
        ->toContain('COLLATE utf8mb4_unicode_ci')
        ->toContain('CREATE USER')
        ->toContain('GRANT ALL PRIVILEGES')
        ->toContain('FLUSH PRIVILEGES');
});

test('database creation script uses proper escaping with backticks', function () {
    $script = view('scripts.create-site-database', [
        'databaseName' => 'example_com',
        'databaseUser' => 'example_com',
        'databasePassword' => 'secure_password',
        'mysqlRootPassword' => 'root_pass',
    ])->render();

    expect($script)
        ->toContain('$DATABASE_NAME')
        ->toContain('$DATABASE_USER')
        ->toContain('@\'%\'');
});

test('handle generates secure random password', function () {
    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result(output: 'mkdir output', exitCode: 0))
            ->push(Process::result(output: 'scp output', exitCode: 0))
            ->push(Process::result(output: 'Database creation complete!', exitCode: 0)),
    ]);

    $job = new CreateSiteDatabase($this->site->id);
    $job->handle();

    $site = $this->site->fresh();

    expect($site->database_password)
        ->not->toBeEmpty()
        ->toHaveLength(32);
});
