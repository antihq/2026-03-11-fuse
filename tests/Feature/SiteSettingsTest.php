<?php

use App\Jobs\InstallSiteQueue;
use App\Jobs\UninstallSiteQueue;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

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
        'hook_after_updating_repository' => 'echo "test hook"',
        'status' => 'ready',
    ]);
});

test('guests cannot access site settings page', function () {
    $this->get(route('servers.sites.settings', [$this->server, $this->site]))
        ->assertRedirect(route('login'));
});

test('user cannot access settings for another users site', function () {
    $anotherUser = User::factory()->create();
    $anotherServer = Server::create([
        'user_id' => $anotherUser->id,
        'name' => 'Another Server',
        'ip_address' => '10.0.0.2',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'provisioned_at' => now(),
    ]);
    $anotherSite = Site::create([
        'server_id' => $anotherServer->id,
        'hostname' => 'another.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'status' => 'ready',
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::sites.settings', ['server' => $anotherServer->id, 'site' => $anotherSite->id])
        ->assertForbidden();
});

test('settings page is displayed', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::sites.settings', ['server' => $this->server->id, 'site' => $this->site->id])
        ->assertSee($this->site->hostname)
        ->assertSee('Hook: Before Updating Repository')
        ->assertSee('Hook: After Updating Repository');
});

test('hooks can be updated', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::sites.settings', ['server' => $this->server->id, 'site' => $this->site->id])
        ->set('hook_before_updating_repository', 'echo "before"')
        ->set('hook_after_updating_repository', 'echo "after"')
        ->call('save')
        ->assertRedirect(route('servers.sites.settings', [$this->server, $this->site]));

    expect($this->site->fresh())
        ->hook_before_updating_repository->toBe('echo "before"')
        ->hook_after_updating_repository->toBe('echo "after"');
});
test('empty hooks can be saved', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::sites.settings', ['server' => $this->server->id, 'site' => $this->site->id])
        ->set('hook_before_updating_repository', '')
        ->set('hook_after_updating_repository', '')
        ->call('save')
        ->assertRedirect(route('servers.sites.settings', [$this->server, $this->site]));

    expect($this->site->fresh())
        ->hook_before_updating_repository->toBeNull()
        ->hook_after_updating_repository->toBeNull();
});

test('load env button is displayed on settings page', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::sites.settings', ['server' => $this->server->id, 'site' => $this->site->id])
        ->assertSee('Environment File')
        ->assertSee('Load .env File');
});

test('loadEnv loads content and sets envLoaded to true', function () {
    $envContent = "APP_ENV=production\nAPP_KEY=base64:test-key\nDB_HOST=localhost\n";

    Process::fake([
        'ssh *' => Process::result(output: $envContent),
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::sites.settings', ['server' => $this->server->id, 'site' => $this->site->id])
        ->call('loadEnv')
        ->assertSet('envContent', $envContent)
        ->assertSet('envLoaded', true);
});

test('loadEnv sets error message on failure', function () {
    Process::fake([
        'ssh *' => Process::result(
            exitCode: 1,
            output: '',
        ),
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::sites.settings', ['server' => $this->server->id, 'site' => $this->site->id])
        ->call('loadEnv')
        ->assertSet('envLoadError', 'Unable to read .env file or file is empty.')
        ->assertSet('envLoaded', false);
});

test('saveEnv saves content successfully', function () {
    Process::fake([
        'scp *' => Process::result(exitCode: 0),
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::sites.settings', ['server' => $this->server->id, 'site' => $this->site->id])
        ->set('envContent', "APP_ENV=production\nAPP_KEY=new-key")
        ->set('envLoaded', true)
        ->call('saveEnv')
        ->assertHasNoErrors()
        ->assertSet('envSaveError', '');
});

test('saveEnv sets error message on failure', function () {
    Process::fake([
        'scp *' => Process::result(
            exitCode: 1,
            errorOutput: 'Permission denied',
        ),
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::sites.settings', ['server' => $this->server->id, 'site' => $this->site->id])
        ->set('envContent', 'APP_ENV=production')
        ->set('envLoaded', true)
        ->call('saveEnv')
        ->assertSet('envSaveError', 'Failed to save .env file.');
});

test('user cannot load env for another users site', function () {
    $anotherUser = User::factory()->withSshKeys()->create();
    $anotherServer = Server::create([
        'user_id' => $anotherUser->id,
        'name' => 'Another Server',
        'ip_address' => '10.0.0.2',
        'ram_mb' => 2048,
        'sites_user' => 'deploy',
        'provisioned_at' => now(),
    ]);
    $anotherSite = Site::create([
        'server_id' => $anotherServer->id,
        'hostname' => 'another.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
        'status' => 'ready',
    ]);

    $this->actingAs($this->user);

    Livewire::actingAs($this->user)
        ->test('pages::sites.settings', ['server' => $anotherServer->id, 'site' => $anotherSite->id])
        ->assertStatus(403);
});

test('queue workers section is displayed on settings page', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::sites.settings', ['server' => $this->server->id, 'site' => $this->site->id])
        ->assertSee('Queue Workers')
        ->assertSee('Number of Worker Processes');
});

test('enableQueue dispatches InstallSiteQueue job', function () {
    Queue::fake();

    $this->actingAs($this->user);

    Livewire::test('pages::sites.settings', ['server' => $this->server->id, 'site' => $this->site->id])
        ->set('queue_processes', '3')
        ->call('enableQueue')
        ->assertRedirect(route('servers.sites.settings', [$this->server, $this->site]));

    Queue::assertPushed(InstallSiteQueue::class);

    expect($this->site->fresh())
        ->queue_enabled->toBeTrue()
        ->queue_processes->toBe(3);
});

test('disableQueue dispatches UninstallSiteQueue job', function () {
    $this->site->update(['queue_enabled' => true]);
    Queue::fake();

    $this->actingAs($this->user);

    Livewire::test('pages::sites.settings', ['server' => $this->server->id, 'site' => $this->site->id])
        ->call('disableQueue')
        ->assertRedirect(route('servers.sites.settings', [$this->server, $this->site]));

    Queue::assertPushed(UninstallSiteQueue::class);

    expect($this->site->fresh()->queue_enabled)->toBeFalse();
});

test('queue_processes can be updated', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::sites.settings', ['server' => $this->server->id, 'site' => $this->site->id])
        ->set('queue_processes', '5')
        ->call('save')
        ->assertRedirect(route('servers.sites.settings', [$this->server, $this->site]));

    expect($this->site->fresh()->queue_processes)->toBe(5);
});

test('queue_processes validation allows values between 1 and 10', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::sites.settings', ['server' => $this->server->id, 'site' => $this->site->id])
        ->set('queue_processes', '0')
        ->call('save')
        ->assertHasErrors(['queue_processes' => 'min']);

    Livewire::test('pages::sites.settings', ['server' => $this->server->id, 'site' => $this->site->id])
        ->set('queue_processes', '11')
        ->call('save')
        ->assertHasErrors(['queue_processes' => 'max']);
});
