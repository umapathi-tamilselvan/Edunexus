<?php

use App\Http\Controllers\SpaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SPA Routes
|--------------------------------------------------------------------------
|
| Catch-all page route for the Vue SPA, registered last. API and Sanctum
| paths are excluded so unknown endpoints still return a JSON 404.
|
*/

Route::get('/{path?}', SpaController::class)
    ->where('path', '^(?!api(/|$)|sanctum(/|$)|up$).*');
