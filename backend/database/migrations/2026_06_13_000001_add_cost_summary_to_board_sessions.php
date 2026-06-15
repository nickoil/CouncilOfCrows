<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('board_sessions', function (Blueprint $table) {
            $table->json('cost_summary')->nullable()->after('selected_tensions');
        });
    }

    public function down(): void
    {
        Schema::table('board_sessions', function (Blueprint $table) {
            $table->dropColumn('cost_summary');
        });
    }
};
