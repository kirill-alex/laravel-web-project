<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\MailboxController;
use App\Http\Controllers\MainController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MainController::class, 'index'])->name('home');
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

Route::middleware('guest')->group(function () {
    Route::redirect('/signin', '/register');
    Route::post('/signin', [AuthController::class, 'register']);

    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);

    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Защищённые маршруты
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/mailbox', [MailboxController::class, 'index'])->name('mailbox.index');
    Route::get('/mailbox/{message}', [MailboxController::class, 'show'])->name('mailbox.show');
    Route::resource('articles', ArticleController::class)->except(['index', 'show']);
    Route::resource('articles.comments', CommentController::class)
        ->only(['store', 'edit', 'update', 'destroy'])->scoped();
});

// Открытые для всех
Route::resource('articles', ArticleController::class)->only(['index', 'show']);
