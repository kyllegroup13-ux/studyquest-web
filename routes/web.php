<?php

use App\Http\Controllers\ClassAttachmentController;
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

Route::post('/class/attachments', [ClassAttachmentController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('class.attachments.store');