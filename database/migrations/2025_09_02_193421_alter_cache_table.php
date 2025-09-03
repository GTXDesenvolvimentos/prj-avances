<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cache', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('key');
            // Exemplo: adicionar índice
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('cache', function (Blueprint $table) {
            $table->dropColumn('user_id');
            $table->dropIndex(['user_id']);
        });
    }
};

