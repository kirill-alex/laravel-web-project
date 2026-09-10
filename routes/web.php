<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

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