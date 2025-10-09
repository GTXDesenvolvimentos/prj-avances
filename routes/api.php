<?php

use App\Http\Controllers\InventoryMovementsController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductCategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductUnitsController;

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);



Route::group(['middleware' => ['jwt.auth']], function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);

    // Products routes
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{id}', [ProductController::class, 'show']);
    Route::post('products', [ProductController::class, 'store']);
    Route::put('products/{id}', [ProductController::class, 'update']);
    Route::delete('products/{id}', [ProductController::class, 'destroy']);

    // Categories routes
    Route::get('categories', [ProductCategoryController::class, 'index']);
    Route::post('categories', [ProductCategoryController::class, 'store']);
    Route::put('categories/{id}', [ProductCategoryController::class, 'update']);
    Route::delete('categories/{id}', [ProductCategoryController::class, 'destroy']);

    // Units routes
    Route::get('units', [ProductUnitsController::class, 'index']);
    Route::get('units/{id}', [ProductUnitsController::class, 'show']);
    Route::post('units', [ProductUnitsController::class, 'store']);
    Route::put('units/{id}', [ProductUnitsController::class, 'update']);
    Route::delete('units/{id}', [ProductUnitsController::class, 'destroy']);

    // Moviments routes
    
    Route::get('inventorymovements', [InventoryMovementsController::class, 'index']);
    Route::get('inventorymovements/{id}', [InventoryMovementsController::class, 'show']);
    Route::post('inventorymovements', [InventoryMovementsController::class, 'store']);
    Route::put('inventorymovements/{id}', [InventoryMovementsController::class, 'update']);
    Route::delete('inventorymovements/{id}', [InventoryMovementsController::class, 'destroy']);
});
