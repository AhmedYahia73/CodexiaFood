<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/docs', function () {
    return redirect('/docs/api');
});

Route::get('/api/docs', function () {
    return redirect('/docs/api');
});
