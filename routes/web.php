<?php

use App\Http\Controllers\ProvisionController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    Route::livewire('servers', 'pages::servers.index')->name('servers.index');
    Route::livewire('servers/create', 'pages::servers.create')->name('servers.create');
    Route::livewire('servers/{server}', 'pages::servers.show')->name('servers.show');
    Route::livewire('servers/{server}/edit', 'pages::servers.edit')->name('servers.edit');
    Route::livewire('servers/{server}/provision', 'pages::servers.provision')->name('servers.provision');
});

Route::get('/provision/{token}', [ProvisionController::class, 'show'])->name('provision.show');

require __DIR__.'/settings.php';
