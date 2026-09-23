<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UniverseController;
use App\Http\Controllers\UniverseImageController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);

    Route::get('/universes', [UniverseController::class, 'index']);
    Route::get('/universes/{universe}', [UniverseController::class, 'show']);
    Route::post('/universes', [UniverseController::class, 'store']);
    Route::put('/universes/{universe}', [UniverseController::class, 'update']);
    Route::delete('/universes/{universe}', [UniverseController::class, 'destroy']);

    Route::post('/universes/{universe}/images', [UniverseImageController::class, 'store']);
    Route::delete('/universes/{universe}/images/{image}', [UniverseImageController::class, 'destroy']);

    Route::post('/items', [ItemController::class, 'store']);

    Route::middleware('admin')->group(function () {
        Route::apiResource('users', UserController::class)->only(['index', 'show', 'destroy']);
    });
});
