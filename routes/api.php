<?php

use App\Http\Controllers\Api\CallbackController;
use Illuminate\Support\Facades\Route;

Route::post('/callback/{task}', CallbackController::class)
    ->name('api.callback')
    ->middleware(['signed', 'throttle:60,1']);
