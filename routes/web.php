<?php

use App\Http\Controllers\SystemController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [SystemController::class, 'health']);
Route::get('/info', [SystemController::class, 'info']);

Route::get('/', function () {
    $index = public_path('index.html');
    if (file_exists($index)) {
        return response()->file($index);
    }

    return view('welcome');
});

Route::get('/pages/{page}', function (string $page) {
    $path = public_path('pages/' . $page);
    if (file_exists($path)) {
        return response()->file($path);
    }

    abort(404);
})->where('page', '.*');
