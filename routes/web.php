<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;

Route::resource('insetProduct', ProductController::class);


Route::get('/', function () {
    return view('welcome');
});
