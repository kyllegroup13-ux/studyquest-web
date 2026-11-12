<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StudentGameActivityController;

Route::post('/class/activities/authorize', [
    StudentGameActivityController::class,
    'authorizeActivity',
])->middleware('throttle:20,1')
    ->name('api.class.activities.authorize');
