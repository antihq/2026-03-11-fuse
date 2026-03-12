<?php

use App\Jobs\ConfigureSite;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 2048,
        'provisioned_at' => now(),
    ]);
});

test('guests cannot access site create page', function () {
    $this->get(route('servers.sites.create', $this->server))->assertRedirect(route('login'));
});

test('user cannot create site on another users server', function () {
    $otherUser = User::factory()->create();
    $otherServer = Server::create([
        'user_id' => $otherUser->id,
        'name' => 'Other Server',
        'ip_address' => '10.0.0.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::sites.create', ['server' => $otherServer->id])
        ->assertRedirect(route('servers.index'));
});

test('user cannot create site on non_provisioned server', function () {
    $unprovisionedServer = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Unprovisioned Server',
        'ip_address' => '10.0.0.2',
        'ram_mb' => 1024,
        'provisioned_at' => null,
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::sites.create', ['server' => $unprovisionedServer->id])
        ->assertRedirect(route('servers.provision', $unprovisionedServer));
});

test('non_existent server throws 404', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::sites.create', ['server' => 99999]);
})->throws(ModelNotFoundException::class);

test('site creation validates required fields', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::sites.create', ['server' => $this->server->id])
        ->set('hostname', '')
        ->set('repository_url', '')
        ->set('repository_branch', '')
        ->call('save')
        ->assertHasErrors(['hostname', 'repository_url', 'repository_branch']);
});

test('site creation validates hostname format', function (string $hostname) {
    $this->actingAs($this->user);

    Livewire::test('pages::sites.create', ['server' => $this->server->id])
        ->set('hostname', $hostname)
        ->set('repository_url', 'git@github.com:user/repo.git')
        ->set('repository_branch', 'main')
        ->call('save')
        ->assertHasErrors('hostname');
})->with([
    '',
    'invalid',
    'http://example.com',
    'example.com/',
    '.example.com',
    'example..com',
    'EXAMPLE.COM',
]);

test('valid hostnames are accepted', function (string $hostname) {
    $this->actingAs($this->user);

    Livewire::test('pages::sites.create', ['server' => $this->server->id])
        ->set('hostname', $hostname)
        ->set('repository_url', 'git@github.com:user/repo.git')
        ->set('repository_branch', 'main')
        ->call('save')
        ->assertHasNoErrors();
})->with([
    'example.com',
    'sub.example.com',
    'my-site.co.uk',
    'a.b.c.example.org',
]);

test('site creation validates hostname is unique per server', function () {
    Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'existing.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::sites.create', ['server' => $this->server->id])
        ->set('hostname', 'existing.com')
        ->set('repository_url', 'git@github.com:user/other.git')
        ->set('repository_branch', 'main')
        ->call('save')
        ->assertHasErrors('hostname');
});

test('same hostname can exist on different servers', function () {
    $otherServer = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Other Server',
        'ip_address' => '10.0.0.3',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);

    Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'shared.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
    ]);

    $this->actingAs($this->user);

    Livewire::test('pages::sites.create', ['server' => $otherServer->id])
        ->set('hostname', 'shared.com')
        ->set('repository_url', 'git@github.com:user/other.git')
        ->set('repository_branch', 'main')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('servers.show', $otherServer->id));

    expect(Site::where('hostname', 'shared.com')->count())->toBe(2);
});

test('site creation validates php_version', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::sites.create', ['server' => $this->server->id])
        ->set('hostname', 'example.com')
        ->set('php_version', '7.4')
        ->set('repository_url', 'git@github.com:user/repo.git')
        ->set('repository_branch', 'main')
        ->call('save')
        ->assertHasErrors('php_version');
});

test('user can create site with valid data', function () {
    Bus::fake();

    $this->actingAs($this->user);

    Livewire::test('pages::sites.create', ['server' => $this->server->id])
        ->set('hostname', 'example.com')
        ->set('php_version', '8.3')
        ->set('repository_url', 'git@github.com:user/repo.git')
        ->set('repository_branch', 'develop')
        ->call('save')
        ->assertRedirect(route('servers.show', $this->server->id));

    $site = Site::where('server_id', $this->server->id)->first();

    expect($site)
        ->hostname->toBe('example.com')
        ->php_version->toBe('8.3')
        ->size->toBe('large')
        ->repository_url->toBe('git@github.com:user/repo.git')
        ->repository_branch->toBe('develop')
        ->status->toBe('pending');

    Bus::assertDispatched(ConfigureSite::class, fn ($job) => $job->siteId === $site->id);
});

test('site defaults php version to 8.4', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::sites.create', ['server' => $this->server->id])
        ->assertSet('php_version', '8.4');
});

test('site defaults branch to main', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::sites.create', ['server' => $this->server->id])
        ->assertSet('repository_branch', 'main');
});

test('site is associated with correct server', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::sites.create', ['server' => $this->server->id])
        ->set('hostname', 'example.com')
        ->set('repository_url', 'git@github.com:user/repo.git')
        ->set('repository_branch', 'main')
        ->call('save');

    $site = Site::where('hostname', 'example.com')->first();

    expect($site->server_id)->toBe($this->server->id);
});

test('redirects to server show after creation', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::sites.create', ['server' => $this->server->id])
        ->set('hostname', 'example.com')
        ->set('repository_url', 'git@github.com:user/repo.git')
        ->set('repository_branch', 'main')
        ->call('save')
        ->assertRedirect(route('servers.show', $this->server->id));
});

test('site creation pre-fills hook_after_updating_repository with default content', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::sites.create', ['server' => $this->server->id])
        ->set('hostname', 'example.com')
        ->set('repository_url', 'git@github.com:user/repo.git')
        ->set('repository_branch', 'main')
        ->call('save');

    $site = Site::where('hostname', 'example.com')->first();

    expect($site->hook_after_updating_repository)
        ->toContain('Installing Composer dependencies')
        ->toContain('artisan config:cache');
});

test('site creation uses correct PHP version in default hook', function () {
    $this->actingAs($this->user);

    Livewire::test('pages::sites.create', ['server' => $this->server->id])
        ->set('hostname', 'example.com')
        ->set('php_version', '8.3')
        ->set('repository_url', 'git@github.com:user/repo.git')
        ->set('repository_branch', 'main')
        ->call('save');

    $site = Site::where('hostname', 'example.com')->first();

    expect($site->hook_after_updating_repository)
        ->toContain('php8.3')
        ->not->toContain('php8.4');
});
