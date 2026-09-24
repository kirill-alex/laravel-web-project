<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MainController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ArticleController;


Route::get('/', [MainController::class, 'index']);
Route::get('/gallery/{id}', [MainController::class, 'gallery']);

Route::get('/about', function () {
    return view('about');
});

Route::get('/contacts', function () {
    $contacts = [
        ['name' => 'Телефон', 'value' => '+7 900 000-00-00'],
        ['name' => 'Email', 'value' => 'example@mail.ru'],
        ['name' => 'Адрес', 'value' => 'г. Москва, ул. Примерная, 1'],
    ];
    return view('contacts', ['contacts' => $contacts]);
});

Route::get('/signin', [AuthController::class, 'create']);
Route::post('/signin', [AuthController::class, 'registration']);
Route::resource('articles', ArticleController::class);

Route::get('/register', [AuthController::class, 'showRegister']);
Route::post('/register', [AuthController::class, 'register']);

Route::get('/login', [AuthController::class, 'showLogin']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/logout', [AuthController::class, 'logout']);

// Защищённые маршруты — пример на статьях
Route::middleware('auth:sanctum')->group(function () {
    Route::resource('articles', ArticleController::class)->except(['index', 'show']);
});

// Открытые для всех
Route::resource('articles', ArticleController::class)->only(['index', 'show']);