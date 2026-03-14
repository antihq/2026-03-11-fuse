<?php

use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $user = User::factory()->withSshKeys()->create();
    $server = Server::factory()->for($user)->create([
        'provisioned_at' => now(),
    ]);
    actingAs($user);

    [$this->user, $this->server] = [$user, $server];
});

test('guests cannot access caddyfile page', function () {
    $site = Site::factory()->for($this->server)->create();

    auth()->logout();

    Livewire::test('pages::sites.caddyfile', [
        'server' => $this->server->id,
        'site' => $site->id,
    ])->assertStatus(403);
});

test('user cannot access caddyfile for another users site', function () {
    $otherUser = User::factory()->withSshKeys()->create();
    $otherServer = Server::factory()->for($otherUser)->create([
        'provisioned_at' => now(),
    ]);
    $site = Site::factory()->for($otherServer)->create();

    Livewire::test('pages::sites.caddyfile', [
        'server' => $otherServer->id,
        'site' => $site->id,
    ])->assertStatus(403);
});

test('caddyfile page is displayed for users own site', function () {
    $site = Site::factory()->for($this->server)->create();

    Livewire::test('pages::sites.caddyfile', [
        'server' => $this->server->id,
        'site' => $site->id,
    ])->assertStatus(200)
        ->assertSee('Edit Caddyfile')
        ->assertSee($site->hostname);
});

test('loadCaddyfile loads content from remote file', function () {
    $site = Site::factory()->for($this->server)->create([
        'hostname' => 'example.com',
    ]);
    $caddyfileContent = "example.com {\n    root * /public\n";

    Process::fake([
        'ssh *' => Process::result(output: $caddyfileContent),
    ]);

    Livewire::test('pages::sites.caddyfile', [
        'server' => $this->server->id,
        'site' => $site->id,
    ])
        ->call('loadCaddyfile')
        ->assertSet('caddyfileContent', $caddyfileContent)
        ->assertSet('caddyfileLoaded', true)
        ->assertSet('loadError', '');
});

test('loadCaddyfile sets error when file is empty', function () {
    $site = Site::factory()->for($this->server)->create([
        'hostname' => 'example.com',
    ]);

    Process::fake([
        'ssh *' => Process::result(output: ''),
    ]);

    Livewire::test('pages::sites.caddyfile', [
        'server' => $this->server->id,
        'site' => $site->id,
    ])
        ->call('loadCaddyfile')
        ->assertSet('loadError', 'Unable to read Caddyfile or file is empty.')
        ->assertSet('caddyfileLoaded', false);
});

test('loadCaddyfile sets error on ssh failure', function () {
    $site = Site::factory()->for($this->server)->create([
        'hostname' => 'example.com',
    ]);

    Process::fake([
        'ssh *' => Process::result(
            exitCode: 1,
            output: '',
        ),
    ]);

    Livewire::test('pages::sites.caddyfile', [
        'server' => $this->server->id,
        'site' => $site->id,
    ])
        ->call('loadCaddyfile')
        ->assertSet('loadError', 'Unable to read Caddyfile or file is empty.')
        ->assertSet('caddyfileLoaded', false);
});

test('saveCaddyfile writes file and reloads caddy', function () {
    $site = Site::factory()->for($this->server)->create([
        'hostname' => 'example.com',
    ]);
    $caddyfileContent = "example.com {\n    root * /public\n";

    Process::fake([
        'scp *' => Process::result(exitCode: 0),
        'ssh *' => Process::result(exitCode: 0),
    ]);

    Livewire::test('pages::sites.caddyfile', [
        'server' => $this->server->id,
        'site' => $site->id,
    ])
        ->set('caddyfileContent', $caddyfileContent)
        ->set('caddyfileLoaded', true)
        ->call('saveCaddyfile')
        ->assertHasNoErrors()
        ->assertSet('caddyfileLoaded', false)
        ->assertSet('caddyfileContent', '');
});

test('saveCaddyfile sets error when write fails', function () {
    $site = Site::factory()->for($this->server)->create([
        'hostname' => 'example.com',
    ]);
    $caddyfileContent = "example.com {\n    root * /public\n";

    Process::fake([
        'scp *' => Process::result(
            exitCode: 1,
            errorOutput: 'Permission denied',
        ),
    ]);

    Livewire::test('pages::sites.caddyfile', [
        'server' => $this->server->id,
        'site' => $site->id,
    ])
        ->set('caddyfileContent', $caddyfileContent)
        ->set('caddyfileLoaded', true)
        ->call('saveCaddyfile')
        ->assertSet('saveError', 'Failed to save Caddyfile.');
});

test('saveCaddyfile sets error when validation fails', function () {
    $site = Site::factory()->for($this->server)->create([
        'hostname' => 'example.com',
    ]);
    $caddyfileContent = 'invalid caddyfile content';

    Process::fake([
        'scp *' => Process::result(exitCode: 0),
        'ssh *' => Process::result(
            exitCode: 1,
            output: "syntax error at line 1\n",
        ),
    ]);

    $result = Livewire::test('pages::sites.caddyfile', [
        'server' => $this->server->id,
        'site' => $site->id,
    ])
        ->set('caddyfileContent', $caddyfileContent)
        ->set('caddyfileLoaded', true)
        ->call('saveCaddyfile');

    expect($result->get('validationError'))
        ->toContain('Caddyfile validation failed')
        ->toContain('syntax error at line 1');
});

test('caddyfilePath returns correct path', function () {
    $site = Site::factory()->for($this->server)->create([
        'hostname' => 'example.com',
    ]);

    expect($site->caddyfilePath())
        ->toBe("/home/{$this->server->sites_user}/example.com/Caddyfile");
});
