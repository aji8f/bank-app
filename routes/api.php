<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Banking API Routes
|--------------------------------------------------------------------------
|
| All routes are open (no authentication) for testing purposes.
| Rate limiting is applied via middleware.
|
*/

// Health check
Route::get('/health', [HealthController::class, 'check']);

// Account endpoints
Route::get('/balance/{user_id}', [AccountController::class, 'balance'])
    ->where('user_id', '[0-9]+');

Route::post('/deposit', [AccountController::class, 'deposit']);

Route::get('/users', [AccountController::class, 'users']);

Route::get('/stats', [AccountController::class, 'stats']);

// Transaction endpoints
Route::post('/transfer', [TransactionController::class, 'transfer']);

Route::get('/transactions/{user_id}', [TransactionController::class, 'history'])
    ->where('user_id', '[0-9]+');
