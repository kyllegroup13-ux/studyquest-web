<?php

use App\Http\Controllers\ClassAttachmentController;
use App\Http\Controllers\ClassPostController;
use App\Http\Controllers\ProfileImageController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClassStudentController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\GameActivityController;

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

Route::delete('/class/students/{studentId}', [ClassStudentController::class, 'destroy'])
    ->middleware('throttle:20,1')
    ->name('class.students.destroy');

Route::delete('/class/{classId}', [ClassController::class, 'destroy'])
    ->middleware('throttle:10,1')
    ->name('class.destroy');

// Create a teacher-assigned game activity draft.
Route::post(
    '/class/activities',
    [GameActivityController::class, 'store']
)
    ->middleware('throttle:10,1')
    ->name('class.activities.store');

Route::post('/class/activities/generate', [
    GameActivityController::class,
    'generate',
])->middleware('throttle:5,1');

Route::post('/class/activities/save-questions', [
    GameActivityController::class,
    'saveQuestions',
])->middleware('throttle:10,1');

// Publish a completed teacher-assigned game activity.
Route::post('/class/activities/publish', [
    GameActivityController::class,
    'publish',
])->middleware('throttle:10,1')
    ->name('class.activities.publish');