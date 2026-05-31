<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurrings', function (Blueprint $table) {
            $table->foreignId('card_id')
                ->nullable()
                ->after('type_id')
                ->constrained('cards')
                ->onUpdate('cascade')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('recurrings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('card_id');
        });
    }
};
