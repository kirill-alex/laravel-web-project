<?php

use App\Http\Controllers\API\ArticleController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CommentController;
use Illuminate\Support\Facades\Route;

Route::name('api.')->group(function (): void {
    Route::middleware('throttle:10,1')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->name('register');
        Route::post('login', [AuthController::class, 'login'])->name('login');
    });
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('user', [AuthController::class, 'user'])->name('user');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::apiResource('articles', ArticleController::class)->except(['index', 'show']);
        Route::apiResource('articles.comments', CommentController::class)
            ->only(['store', 'update', 'destroy'])->scoped();
    });
    Route::apiResource('articles', ArticleController::class)->only(['index', 'show'])
        ->middlewareFor('show', 'track.article.view');
});
