<?php

use App\Http\Controllers\SshSetupController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', '/servers')->name('dashboard');
    Route::livewire('servers', 'pages::servers.index')->name('servers.index');
    Route::livewire('servers/create', 'pages::servers.create')->name('servers.create');
    Route::livewire('servers/{server}', 'pages::servers.show')->name('servers.show');
    Route::livewire('servers/{server}/edit', 'pages::servers.edit')->name('servers.edit');
    Route::livewire('servers/{server}/provision', 'pages::servers.provision')->name('servers.provision');
    Route::livewire('servers/{server}/sites/create', 'pages::sites.create')->name('servers.sites.create');
    Route::livewire('servers/{server}/sites/{site}/settings', 'pages::sites.settings')->name('servers.sites.settings');
    Route::livewire('servers/{server}/sites/{site}/caddyfile', 'pages::sites.caddyfile')->name('servers.sites.caddyfile');
});

Route::get('/ssh-setup/{token}', [SshSetupController::class, 'show'])->name('ssh-setup.show');
Route::get('/ssh-setup/{token}/callback', [SshSetupController::class, 'callback'])->name('ssh-setup.callback');

require __DIR__.'/settings.php';
