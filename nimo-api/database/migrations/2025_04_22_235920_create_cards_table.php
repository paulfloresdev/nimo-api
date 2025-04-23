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
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->string('numbers');
            $table->string('color');
            $table->foreignId('type_id')->unsigned()->constrained('account_types')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('bank_id')->unsigned()->constrained('banks')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('network_id')->unsigned()->constrained('networks')->onUpdate('cascade')->onDelete('restrict');
            $table->foreignId('user_id')->unsigned()->constrained('users')->onUpdate('cascade')->onDelete('restrict');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
