<?php

use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use App\Policies\SitePolicy;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();
    $this->server = Server::create([
        'user_id' => $this->user->id,
        'name' => 'Test Server',
        'ip_address' => '192.168.1.1',
        'ram_mb' => 1024,
        'provisioned_at' => now(),
    ]);
    $this->site = Site::create([
        'server_id' => $this->server->id,
        'hostname' => 'example.com',
        'php_version' => '8.4',
        'size' => 'large',
        'repository_url' => 'git@github.com:user/repo.git',
        'repository_branch' => 'main',
    ]);
});

test('delete returns true when user owns the server', function () {
    $policy = new SitePolicy;

    expect($policy->delete($this->user, $this->site))->toBeTrue();
});

test('delete returns false when user does not own the server', function () {
    $policy = new SitePolicy;

    expect($policy->delete($this->otherUser, $this->site))->toBeFalse();
});

test('view returns true when user owns the server', function () {
    $policy = new SitePolicy;

    expect($policy->view($this->user, $this->site))->toBeTrue();
});

test('view returns false when user does not own the server', function () {
    $policy = new SitePolicy;

    expect($policy->view($this->otherUser, $this->site))->toBeFalse();
});

test('update returns true when user owns the server', function () {
    $policy = new SitePolicy;

    expect($policy->update($this->user, $this->site))->toBeTrue();
});

test('update returns false when user does not own the server', function () {
    $policy = new SitePolicy;

    expect($policy->update($this->otherUser, $this->site))->toBeFalse();
});
