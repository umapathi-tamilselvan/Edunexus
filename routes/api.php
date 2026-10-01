<?php

use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\Auth\TokenController;
use App\Http\Controllers\Api\V1\BrandingController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (/api/v1)
|--------------------------------------------------------------------------
|
| Consumed by the Vue SPA (Sanctum cookie session) and the Flutter app
| (Sanctum bearer tokens). This deployment serves exactly one client.
|
*/

Route::get('branding', BrandingController::class)->name('branding');

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('login', [SessionController::class, 'store'])->middleware('throttle:login')->name('login');
    Route::post('tokens', [TokenController::class, 'store'])->middleware('throttle:login')->name('tokens.store');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [SessionController::class, 'destroy'])->name('logout');
        Route::delete('tokens/current', [TokenController::class, 'destroy'])->name('tokens.destroy');
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('me', MeController::class)->name('me');

    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
    Route::apiResource('users', UserController::class)->only(['index', 'store']);
    Route::put('users/{user}/roles', [UserController::class, 'updateRoles'])->name('users.roles.update');
});
