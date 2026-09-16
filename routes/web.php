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
Route::get('/articles', [ArticleController::class, 'index']);