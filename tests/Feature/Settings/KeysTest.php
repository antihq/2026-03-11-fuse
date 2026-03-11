<?php

use App\Models\User;
use Livewire\Livewire;

test('keys page is displayed', function () {
    $user = User::factory()->withSshKeys()->create();

    $this->actingAs($user);

    $this->get(route('keys.show'))->assertOk();
});

test('keys page displays ssh keys', function () {
    $user = User::factory()->withSshKeys()->create();

    $this->actingAs($user);

    Livewire::test('pages::settings.keys')
        ->assertSee($user->ssh_public_key)
        ->assertSee($user->ssh_private_key);
});
