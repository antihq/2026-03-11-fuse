<?php

use App\Models\Team;
use App\Models\User;

test('user registration creates a personal team', function () {
    $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertRedirect(route('dashboard'));

    $user = User::where('email', 'john@example.com')->first();
    expect($user->team)->not->toBeNull()
        ->and($user->team->name)->toBe('John Doe')
        ->and($user->team->user_id)->toBe($user->id);
});

test('registration rolls back if user creation fails', function () {
    User::creating(function ($user) {
        throw new Exception('User creation failed');
    });

    $this->post(route('register.store'), [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ])->assertStatus(500);

    expect(Team::where('name', 'John Doe')->exists())->toBeFalse();
});

test('user has one team relationship', function () {
    $team = Team::factory()->for(User::factory())->create();

    $user = $team->user;

    expect($user->team->is($team))->toBeTrue();
});

test('team belongs to user relationship', function () {
    $user = User::factory()->has(Team::factory())->create();

    $team = $user->team;

    expect($team->user->is($user))->toBeTrue();
});

test('deleting user cascades to_team', function () {
    $user = User::factory()->has(Team::factory())->create();

    $teamId = $user->team->id;

    $user->delete();

    expect(Team::find($teamId))->toBeNull();
});
