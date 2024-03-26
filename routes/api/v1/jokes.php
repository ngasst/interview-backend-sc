
<?php

use App\Http\Controllers\v1\JokeController;
use App\Providers\RouteServiceProvider as RSP;
use Illuminate\Support\Facades\Route;

/**
 * Jokes
 */
Route::controller(JokeController::class) // middlewares are defined in controller
    ->prefix('jokes')
    ->group(function () {
        Route::get('/random-joke', [JokeController::class, 'fetchRandomJoke']);
    });
