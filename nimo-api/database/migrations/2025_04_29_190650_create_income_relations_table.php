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
        Schema::create('income_relations', function (Blueprint $table) {
            $table->id();
            $table->double('amount', 10, 2);
            $table->foreignId('from_id')->constrained('transactions')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('to_id')->constrained('transactions')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('contact_id')->constrained('contacts')->onUpdate('cascade')->onDelete('restrict');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('income_relations');
    }
};
