<?php

use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

test('keys page is displayed', function () {
    $user = User::factory()->has(Team::factory())->create();

    $this->actingAs($user);

    $this->get(route('keys.show'))->assertOk();
});

test('keys page displays ssh keys', function () {
    $user = User::factory()->has(
        Team::factory()->state([
            'ssh_public_key' => 'ssh-ed25519 AAAA... test@example.com',
            'ssh_private_key' => '-----BEGIN OPENSSH PRIVATE KEY-----',
        ])
    )->create();

    $this->actingAs($user);

    Livewire::test('pages::settings.keys')
        ->assertSee('ssh-ed25519 AAAA... test@example.com')
        ->assertSee('-----BEGIN OPENSSH PRIVATE KEY-----');
});

test('user without team receives 404', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::settings.keys')->assertStatus(404);
});
