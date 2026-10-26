<?php

use App\Http\Controllers\SpaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SPA Routes
|--------------------------------------------------------------------------
|
| Catch-all page route for the Vue SPA, registered last. API, Sanctum and
| Horizon paths are excluded so unknown endpoints still return a JSON 404
| and the queue dashboard is never served as the app shell.
|
*/

Route::get('/{path?}', SpaController::class)
    ->where('path', '^(?!api(/|$)|sanctum(/|$)|horizon(/|$)|up$).*');
