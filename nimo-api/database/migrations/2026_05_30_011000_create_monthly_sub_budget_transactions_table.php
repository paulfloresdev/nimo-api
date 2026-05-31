<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_sub_budget_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_sub_budget_id')->constrained('monthly_sub_budgets')->cascadeOnDelete();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['monthly_sub_budget_id', 'transaction_id'], 'sub_budget_transaction_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_sub_budget_transactions');
    }
};
