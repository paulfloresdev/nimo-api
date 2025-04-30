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
        Schema::create('recurrings', function (Blueprint $table) {
            $table->id();
            $table->string('concept');
            $table->double('amount');
            $table->foreignId('category_id')->unsigned()->constrained('categories')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('type_id')->unsigned()->constrained('transaction_types')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('user_id')->unsigned()->constrained('users')->onUpdate('cascade')->onDelete('restrict');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurrings');
    }
};
