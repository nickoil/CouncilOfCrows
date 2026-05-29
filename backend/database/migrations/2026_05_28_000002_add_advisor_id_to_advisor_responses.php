<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('advisor_responses', function (Blueprint $table) {
            $table->foreignId('advisor_id')
                  ->nullable()
                  ->after('board_session_id')
                  ->constrained()
                  ->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('advisor_responses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('advisor_id');
        });
    }
};
