<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('recurring_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_id')->unsigned()->constrained('recurrings')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('transaction_id')->unsigned()->constrained('transactions')->onUpdate('cascade')->onDelete('restrict');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_records');
    }
};
