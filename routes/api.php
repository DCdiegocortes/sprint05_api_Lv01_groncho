<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ExchangeController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ItemImageController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileAvatarController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\SwipeController;
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
    Route::post('/profile/avatar', [ProfileAvatarController::class, 'store']);
    Route::delete('/profile/avatar', [ProfileAvatarController::class, 'destroy']);

    Route::get('/universes', [UniverseController::class, 'index']);
    Route::get('/universes/feed', [UniverseController::class, 'feed']);
    Route::get('/universes/mine', [UniverseController::class, 'mine']);
    Route::get('/universes/{universe}', [UniverseController::class, 'show']);
    Route::post('/universes', [UniverseController::class, 'store']);
    Route::put('/universes/{universe}', [UniverseController::class, 'update']);
    Route::delete('/universes/{universe}', [UniverseController::class, 'destroy']);

    Route::post('/universes/{universe}/images', [UniverseImageController::class, 'store']);
    Route::put('/universes/{universe}/images/order', [UniverseImageController::class, 'reorder']);
    Route::delete('/universes/{universe}/images/{image}', [UniverseImageController::class, 'destroy']);

    Route::get('/items/ranking', [RankingController::class, 'items']);

    Route::get('/items', [ItemController::class, 'index']);
    Route::get('/items/{item}', [ItemController::class, 'show']);
    Route::post('/items', [ItemController::class, 'store']);
    Route::put('/items/{item}', [ItemController::class, 'update']);
    Route::delete('/items/{item}', [ItemController::class, 'destroy']);

    Route::post('/items/{item}/images', [ItemImageController::class, 'store']);
    Route::delete('/items/{item}/images/{image}', [ItemImageController::class, 'destroy']);

    Route::post('/swipes', [SwipeController::class, 'store']);

    Route::get('/matches', [MatchController::class, 'index']);
    Route::get('/matches/{match}/items', [MatchController::class, 'items']);
    Route::delete('/matches/{match}', [MatchController::class, 'destroy']);

    Route::get('/exchanges', [ExchangeController::class, 'index']);
    Route::get('/exchanges/{exchange}', [ExchangeController::class, 'show']);
    Route::post('/exchanges', [ExchangeController::class, 'store']);
    Route::put('/exchanges/{exchange}', [ExchangeController::class, 'update']);
    Route::delete('/exchanges/{exchange}', [ExchangeController::class, 'destroy']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);

    Route::get('/users/ranking', [RankingController::class, 'users']);
    Route::get('/users/ranking/best', [RankingController::class, 'usersBest']);
    Route::get('/users/ranking/worst', [RankingController::class, 'usersWorst']);

    Route::middleware('admin')->group(function () {
        Route::apiResource('users', UserController::class)->only(['index', 'show', 'destroy']);
    });
});
