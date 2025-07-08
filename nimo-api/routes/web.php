<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\IncomeRelationController;

Route::get('/income-relations/export', [IncomeRelationController::class, 'exportExcel'])->name('income-relations.export');
