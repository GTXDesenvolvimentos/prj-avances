<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);



Route::group(['middleware' => ['jwt.auth']], function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('product', [ProductController::class, 'create']);
    Route::post('category', [ProductCategoryController::class, 'create']);
    Route::put('category', [ProductCategoryController::class, 'update']);
});
