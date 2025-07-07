<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AccountTypesController;
use App\Http\Controllers\NetworkController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\TransactionTypeController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\IncomeRelationController;
use App\Http\Controllers\RecurringController;
use App\Http\Controllers\RecurringRecordController;

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

    // NETWORKS
    Route::apiResource('networks', NetworkController::class);
    Route::post('/networks/{id}/update', [NetworkController::class, 'update']);

    // BANKS
    Route::apiResource('banks', BankController::class);
    Route::post('/banks/{id}/update', [BankController::class, 'update']);

    // CARDS
    Route::apiResource('cards', CardController::class);

    // TRANSACTION TYPES
    Route::apiResource('transaction-types', TransactionTypeController::class);

    // CONTACTS
    Route::apiResource('contacts', ContactController::class);

    // CATEGORIES
    Route::apiResource('categories', CategoryController::class);

    // TRANSACTIONS
    Route::apiResource('transactions', TransactionController::class);
    Route::get('/getYearsWith', [TransactionController::class, 'getYearsWith']);
    Route::post('/getMonthsWith', [TransactionController::class, 'getMonthsWith']);
    Route::get('/getCardsBalance/{year}/{month}', [TransactionController::class, 'getCardsBalance']);
    Route::get('/getMonthBalance/{year}/{month}', [TransactionController::class, 'getMonthBalance']);
    Route::post('/getTransactions/{year}/{month}', [TransactionController::class, 'getTransactions']);

    // INCOME RELATIONS
    Route::apiResource('income-relations', IncomeRelationController::class);
    Route::post('/verify-income-relation', [IncomeRelationController::class, 'verifyIncomeRelation']);
    Route::post('/income-relations/all', [IncomeRelationController::class, 'getAllFiltered']);

    // RECURRINGS
    Route::apiResource('recurrings', RecurringController::class);

    // RECURRING RECORDS
    Route::apiResource('recurring-records', RecurringRecordController::class);
});
