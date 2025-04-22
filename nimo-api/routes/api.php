<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AccountTypesController;
use App\Http\Controllers\NetworkController;

// AUTH
Route::post('/auth/signup', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    // AUTH
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::patch('/auth/update-data/{id}', [AuthController::class, 'updateData']);
    Route::patch('/auth/update-password/{id}', [AuthController::class, 'updatePassword']);

    // ACCOUNT TYPES
    Route::apiResource('account-types', AccountTypesController::class);

    // NETWORK
    Route::apiResource('networks', NetworkController::class);
    Route::post('/networks-update/{id}', [NetworkController::class, 'update']);
});
