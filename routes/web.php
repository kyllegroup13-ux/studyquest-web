<?php

use App\Http\Controllers\ClassAttachmentController;
use App\Http\Controllers\ClassPostController;
use App\Http\Controllers\ProfileImageController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

Route::get('/dashboard', function () {
    return view('dashboard');
});

Route::get('/class/{classId}', function ($classId) {
    return view('class', [
        'classId' => $classId,
    ]);
});

Route::post('/class/attachments', [ClassAttachmentController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('class.attachments.store');

Route::post('/class/posts', [ClassPostController::class, 'publish'])
    ->middleware('throttle:20,1')
    ->name('class.posts.publish');

Route::patch('/class/posts/{postId}', [ClassPostController::class, 'update'])
    ->middleware('throttle:20,1')
    ->name('class.posts.update');

Route::delete('/class/posts/{postId}', [ClassPostController::class, 'destroy'])
    ->middleware('throttle:20,1')
    ->name('class.posts.destroy');

Route::post('/profile/image', [ProfileImageController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('profile.image.store');
