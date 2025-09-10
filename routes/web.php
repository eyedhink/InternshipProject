<?php

use Illuminate\Support\Facades\Route;

Route::any('/w', function () {
    return view('welcome');
});
