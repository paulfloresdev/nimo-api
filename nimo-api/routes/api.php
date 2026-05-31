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
use App\Http\Controllers\AdminController;
use App\Http\Controllers\MonthlySubBudgetController;

// AUTH
Route::post('/auth/signup', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    // AUTH
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::patch('/auth/update-data/{id}', [AuthController::class, 'updateData']);
    Route::patch('/auth/update-password/{id}', [AuthController::class, 'updatePassword']);

    // ADMIN
    Route::prefix('admin')->middleware('role:Admin')->group(function () {
        Route::get('/stats', [AdminController::class, 'stats']);
        Route::get('/users', [AdminController::class, 'users']);
        Route::post('/users', [AdminController::class, 'storeUser']);
        Route::put('/users/{user}', [AdminController::class, 'updateUser']);
        Route::delete('/users/{user}', [AdminController::class, 'destroyUser']);
    });

    // ACCOUNT TYPES
    Route::apiResource('account-types', AccountTypesController::class);

    // NETWORKS
    Route::apiResource('networks', NetworkController::class)->only(['index', 'show']);
    Route::middleware('role:Admin')->group(function () {
        Route::apiResource('networks', NetworkController::class)->only(['store', 'update', 'destroy']);
        Route::post('/networks/{id}/update', [NetworkController::class, 'update']);
    });

    // BANKS
    Route::apiResource('banks', BankController::class)->only(['index', 'show']);
    Route::middleware('role:Admin')->group(function () {
        Route::apiResource('banks', BankController::class)->only(['store', 'update', 'destroy']);
        Route::post('/banks/{id}/update', [BankController::class, 'update']);
    });

    // CARDS
    Route::apiResource('cards', CardController::class);

    // TRANSACTION TYPES
    Route::apiResource('transaction-types', TransactionTypeController::class);

    // CONTACTS
    Route::apiResource('contacts', ContactController::class);

    // CATEGORIES
    Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
    Route::apiResource('categories', CategoryController::class)
        ->only(['store', 'update', 'destroy'])
        ->middleware('role:Admin');

    // TRANSACTIONS
    Route::apiResource('transactions', TransactionController::class);
    Route::get('/getYearsWith', [TransactionController::class, 'getYearsWith']);
    Route::get('/getMonthsWith', [TransactionController::class, 'getMonthsWith']);
    Route::get('/getCardsBalance/{year}/{month}', [TransactionController::class, 'getCardsBalance']);
    Route::get('/getMonthBalance/{year}/{month}', [TransactionController::class, 'getMonthBalance']);
    Route::post('/getTransactions/{year}/{month}', [TransactionController::class, 'getTransactions']);
    Route::post('/searchTransactions', [TransactionController::class, 'searchTransactions']);
    Route::post('/generateFromRecurring', [TransactionController::class, 'generateFromRecurring']);
    Route::post('/getMonthlyExpenseStats/{year}/{month}', [TransactionController::class, 'getMonthlyExpenseStats']);
    Route::get('/getDebitBalanceByPeriod/{year}/{month}/{period}', [TransactionController::class, 'getDebitBalanceByPeriod']);

    // MONTHLY SUB BUDGETS
    Route::get('/monthly-sub-budgets', [MonthlySubBudgetController::class, 'index']);
    Route::post('/monthly-sub-budgets', [MonthlySubBudgetController::class, 'store']);
    Route::put('/monthly-sub-budgets/{monthlySubBudget}', [MonthlySubBudgetController::class, 'update']);
    Route::delete('/monthly-sub-budgets/{monthlySubBudget}', [MonthlySubBudgetController::class, 'destroy']);
    Route::get('/monthly-sub-budgets/{monthlySubBudget}/available-transactions', [MonthlySubBudgetController::class, 'availableTransactions']);
    Route::post('/monthly-sub-budgets/{monthlySubBudget}/transactions', [MonthlySubBudgetController::class, 'attachTransactions']);
    Route::delete('/monthly-sub-budgets/{monthlySubBudget}/transactions/{transaction}', [MonthlySubBudgetController::class, 'detachTransaction']);
    Route::post('/monthly-sub-budgets/{monthlySubBudget}/recalculate', [MonthlySubBudgetController::class, 'recalculate']);

    // INCOME RELATIONS
    Route::apiResource('income-relations', IncomeRelationController::class);
    Route::post('/verify-income-relation', [IncomeRelationController::class, 'verifyIncomeRelation']);
    Route::post('/income-relations/all', [IncomeRelationController::class, 'getAllFiltered']);

    // RECURRINGS
    Route::apiResource('recurrings', RecurringController::class);

    // RECURRING RECORDS
    Route::apiResource('recurring-records', RecurringRecordController::class);
});
