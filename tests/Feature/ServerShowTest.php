<?php

use App\Jobs\DeploySite;
use App\Jobs\UninstallSite;
use App\Models\Server;
use App\Models\Site;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('guests cannot access servers show page', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    $this->get(route('servers.show', $server))->assertRedirect(route('login'));
});

test('user can view a provisioned server', function () {
    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Production Server',
        'ip_address' => '192.168.1.100',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'authorized_keys' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5 test@example.com',
        'provisioned_at' => now(),
    ]);

    Livewire::test('pages::servers.show', ['server' => $server->id])
        ->assertSee('Production Server')
        ->assertSee('192.168.1.100')
        ->assertSee('2,048 MB')
        ->assertSee('deploy')
        ->assertSee('1 configured')
        ->assertSee('Provisioned');
});

test('non-provisioned server redirects to provision page', function () {
    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Pending Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => null,
    ]);

    Livewire::test('pages::servers.show', ['server' => $server->id])
        ->assertRedirect(route('servers.provision', $server));
});

test('user cannot view servers from other users', function () {
    $otherUser = User::factory()->create();
    $otherServer = Server::create([
        'user_id' => $otherUser->id,
        'name' => 'Other Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::servers.show', ['server' => $otherServer->id])
        ->assertRedirect(route('servers.index'));
});

test('show page returns 404 for non-existent server', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::servers.show', ['server' => 99999]);
})->throws(ModelNotFoundException::class);

test('user can delete server from show page', function () {
    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'To Delete',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    Livewire::test('pages::servers.show', ['server' => $server->id])
        ->call('delete')
        ->assertRedirect(route('servers.index'));

    expect(Server::find($server->id))->toBeNull();
});

test('user cannot delete servers from other users via show page', function () {
    $otherUser = User::factory()->create();
    $otherServer = Server::create([
        'user_id' => $otherUser->id,
        'name' => 'Other Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::servers.show', ['server' => $otherServer->id])
        ->assertRedirect(route('servers.index'));

    expect(Server::find($otherServer->id))->not->toBeNull();
});

test('test connection shows success message when connection succeeds', function () {
    $user = User::factory()->withSshKeys()->create();
    $server = Server::create([
        'user_id' => $user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result(output: 'mkdir output', exitCode: 0))
            ->push(Process::result(output: 'scp output', exitCode: 0))
            ->push(Process::result(output: "Connected to test-server\nLinux test-server", exitCode: 0)),
    ]);

    Livewire::actingAs($user)
        ->test('pages::servers.show', ['server' => $server->id])
        ->call('testConnection')
        ->assertSet('connectionStatus', 'Connection successful!')
        ->assertSet('connectionSuccess', true);

    $task = Task::where('user_id', $user->id)
        ->where('server_id', $server->id)
        ->first();

    expect($task)->not->toBeNull()
        ->and($task->status)->toBe('finished')
        ->and($task->exit_code)->toBe(0);
});

test('test connection shows failure message when connection fails', function () {
    $user = User::factory()->withSshKeys()->create();
    $server = Server::create([
        'user_id' => $user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    Process::fake([
        '*' => Process::sequence()
            ->push(Process::result(output: 'mkdir output', exitCode: 0))
            ->push(Process::result(output: 'scp output', exitCode: 0))
            ->push(Process::result(output: 'Permission denied', exitCode: 255)),
    ]);

    Livewire::actingAs($user)
        ->test('pages::servers.show', ['server' => $server->id])
        ->call('testConnection')
        ->assertSet('connectionSuccess', false)
        ->assertSet('connectionStatus', fn ($status) => str_contains($status, 'Permission denied'));

    $task = Task::where('user_id', $user->id)
        ->where('server_id', $server->id)
        ->first();

    expect($task)->not->toBeNull()
        ->and($task->status)->toBe('finished')
        ->and($task->exit_code)->toBe(255);
});

test('test connection shows error when user has no ssh key', function () {
    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::servers.show', ['server' => $server->id])
        ->call('testConnection')
        ->assertSet('connectionStatus', 'No SSH private key configured for your account.')
        ->assertSet('connectionSuccess', false);

    expect(Task::where('user_id', $this->user->id)->count())->toBe(0);
});

test('show page displays sites section', function () {
    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    Livewire::test('pages::servers.show', ['server' => $server->id])
        ->assertSee('Sites');
});

test('show page shows no sites message when empty', function () {
    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    Livewire::test('pages::servers.show', ['server' => $server->id])
        ->assertSee('No sites configured yet.');
});

test('show page lists sites with hostname php version and branch', function () {
    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    Site::create([
        'server_id' => $server->id,
        'hostname' => 'example.com',
        'php_version' => '8.3',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'develop',
    ]);

    Livewire::test('pages::servers.show', ['server' => $server->id])
        ->assertSee('example.com')
        ->assertSee('8.3')
        ->assertSee('develop');
});

test('show page has add site button linking to create page', function () {
    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    Livewire::test('pages::servers.show', ['server' => $server->id])
        ->assertSee('Add Site')
        ->assertSee(route('servers.sites.create', $server));
});

test('show page displays site status', function () {
    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    Site::create([
        'server_id' => $server->id,
        'hostname' => 'active.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'status' => 'active',
    ]);

    Site::create([
        'server_id' => $server->id,
        'hostname' => 'failed.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'status' => 'failed',
    ]);

    Site::create([
        'server_id' => $server->id,
        'hostname' => 'pending.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'status' => 'pending',
    ]);

    Livewire::test('pages::servers.show', ['server' => $server->id])
        ->assertSee('Active')
        ->assertSee('Failed')
        ->assertSee('Pending');
});

test('show page displays ready status', function () {
    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    Site::create([
        'server_id' => $server->id,
        'hostname' => 'ready.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'status' => 'ready',
    ]);

    Livewire::test('pages::servers.show', ['server' => $server->id])
        ->assertSee('Ready to Deploy');
});

test('show page displays deploying status', function () {
    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    Site::create([
        'server_id' => $server->id,
        'hostname' => 'deploying.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'status' => 'deploying',
    ]);

    Livewire::test('pages::servers.show', ['server' => $server->id])
        ->assertSee('Deploying...');
});

test('show page shows deploy button for ready sites', function () {
    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    Site::create([
        'server_id' => $server->id,
        'hostname' => 'ready.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'status' => 'ready',
    ]);

    Livewire::test('pages::servers.show', ['server' => $server->id])
        ->assertSee('Deploy');
});

test('show page shows deploy again button for active sites', function () {
    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    Site::create([
        'server_id' => $server->id,
        'hostname' => 'active.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'status' => 'active',
    ]);

    Livewire::test('pages::servers.show', ['server' => $server->id])
        ->assertSee('Deploy Again');
});

test('show page does not show deploy button for non-deployable statuses', function (string $status) {
    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    Site::create([
        'server_id' => $server->id,
        'hostname' => 'test.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'status' => $status,
    ]);

    $html = Livewire::test('pages::servers.show', ['server' => $server->id])->html();

    expect($html)->not->toContain('wire:click="deploy');
})->with(['deploying', 'configuring', 'pending', 'failed']);

test('deploy method dispatches deploy site job for ready site', function () {
    Bus::fake();

    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    $site = Site::create([
        'server_id' => $server->id,
        'hostname' => 'ready.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'status' => 'ready',
    ]);

    Livewire::test('pages::servers.show', ['server' => $server->id])
        ->call('deploy', $site->id);

    Bus::assertDispatched(DeploySite::class, fn ($job) => $job->siteId === $site->id);
});

test('deploy method dispatches deploy site job for active site', function () {
    Bus::fake();

    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    $site = Site::create([
        'server_id' => $server->id,
        'hostname' => 'active.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'status' => 'active',
    ]);

    Livewire::test('pages::servers.show', ['server' => $server->id])
        ->call('deploy', $site->id);

    Bus::assertDispatched(DeploySite::class, fn ($job) => $job->siteId === $site->id);
});

test('deploy method does not dispatch for deploying site', function () {
    Bus::fake();

    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    $site = Site::create([
        'server_id' => $server->id,
        'hostname' => 'deploying.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'status' => 'deploying',
    ]);

    Livewire::test('pages::servers.show', ['server' => $server->id])
        ->call('deploy', $site->id);

    Bus::assertNotDispatched(DeploySite::class);
});

test('delete site dispatches uninstall job with correct params', function () {
    Bus::fake();

    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    $site = Site::create([
        'server_id' => $server->id,
        'hostname' => 'delete-me.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'status' => 'active',
    ]);

    Livewire::test('pages::servers.show', ['server' => $server->id])
        ->call('deleteSite', $site->id);

    Bus::assertDispatched(UninstallSite::class, fn ($job) => $job->siteId === $site->id);
});

test('delete site does not immediately remove site from database', function () {
    Bus::fake();

    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    $site = Site::create([
        'server_id' => $server->id,
        'hostname' => 'delete-me.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'status' => 'active',
    ]);

    Livewire::test('pages::servers.show', ['server' => $server->id])
        ->call('deleteSite', $site->id);

    expect(Site::find($site->id))->not->toBeNull();
});

test('user cannot delete sites from other users servers', function () {
    Bus::fake();

    $otherUser = User::factory()->create();
    $otherServer = Server::create([
        'user_id' => $otherUser->id,
        'name' => 'Other Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    $site = Site::create([
        'server_id' => $otherServer->id,
        'hostname' => 'other-site.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'status' => 'active',
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::servers.show', ['server' => $otherServer->id])
        ->assertRedirect(route('servers.index'));

    Bus::assertNotDispatched(UninstallSite::class);
    expect(Site::find($site->id))->not->toBeNull();
});

test('show page displays delete button for sites', function () {
    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    Site::create([
        'server_id' => $server->id,
        'hostname' => 'example.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'status' => 'active',
    ]);

    Livewire::test('pages::servers.show', ['server' => $server->id])
        ->assertSee('Delete');
});

test('show page displays settings link for sites', function () {
    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    $site = Site::create([
        'server_id' => $server->id,
        'hostname' => 'example.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'status' => 'active',
    ]);

    Livewire::test('pages::servers.show', ['server' => $server->id])
        ->assertSee('Settings')
        ->assertSee(route('servers.sites.settings', [$server, $site]));
});

test('show page displays caddyfile link for sites', function () {
    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    $site = Site::create([
        'server_id' => $server->id,
        'hostname' => 'example.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'status' => 'active',
    ]);

    Livewire::test('pages::servers.show', ['server' => $server->id])
        ->assertSee('Caddyfile')
        ->assertSee(route('servers.sites.caddyfile', [$server, $site]));
});

test('show page displays deploy key when server has one', function () {
    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Production Server',
        'ip_address' => '192.168.1.100',
        'ram_mb' => 2048,
        'provisioned_at' => now(),
        'server_public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5 deploy@server',
    ]);

    Livewire::test('pages::servers.show', ['server' => $server->id])
        ->assertSee('Deploy Key')
        ->assertSee('ssh-ed25519 AAAAC3NzaC1lZDI1NTE5 deploy@server')
        ->assertSee('Add this public key');
});

test('show page hides deploy key section when server has no key', function () {
    $this->actingAs($this->user);

    $server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Production Server',
        'ip_address' => '192.168.1.100',
        'ram_mb' => 2048,
        'provisioned_at' => now(),
        'server_public_key' => null,
    ]);

    Livewire::test('pages::servers.show', ['server' => $server->id])
        ->assertDontSee('Deploy Key')
        ->assertDontSee('Add this public key');
});
