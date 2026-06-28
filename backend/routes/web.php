<?php

use Illuminate\Support\Facades\Route;

Route::get('/{path?}', function (?string $path = null) {
    $frontend = public_path('index.html');

    if (is_file($frontend)) {
        return response()->file($frontend);
    }

    abort_if($path !== null, 404);

    return view('welcome');
})
    ->where('path', '^(?!api(?:/|$)|up$).*$')
    ->name('frontend');
