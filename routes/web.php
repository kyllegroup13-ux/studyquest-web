<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::get('/dashboard', function () {
    return view('dashboard');
});

Route::get('/class/{classId}', function ($classId) {
    return view('class', [
        'classId' => $classId
    ]);
});
