<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StudentGameActivityController;
use App\Http\Controllers\StudentActivityAttemptController;
use App\Http\Controllers\StudentActivityCompletionController;
use App\Http\Controllers\StudentActivityQuestionController;

// Step 3.2A.2 — Activity authorization
Route::post('/class/activities/authorize', [
    StudentGameActivityController::class,
    'authorizeActivity',
])->middleware('throttle:20,1')
    ->name('api.class.activities.authorize');

// Step 3.2A.3B — Start or resume attempt
Route::post('/class/activities/start', [
    StudentActivityAttemptController::class,
    'start',
])->middleware('throttle:20,1')
    ->name('api.class.activities.start');

// Step 3.2A.3C.2 — Complete and grade attempt
Route::post('/class/activities/complete', [
    StudentActivityCompletionController::class,
    'complete',
])->middleware('throttle:20,1')
    ->name('api.class.activities.complete');

Route::post('/class/activities/questions', [
    StudentActivityQuestionController::class,
    'questions',
])->middleware('throttle:20,1')
    ->name('api.class.activities.questions'); 