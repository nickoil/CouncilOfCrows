<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('board_sessions', function (Blueprint $table) {
            $table->json('advisor_failures')->nullable()->after('consensus');
            $table->text('failure_reason')->nullable()->after('advisor_failures');
            $table->json('active_advisor_ids')->nullable()->after('failure_reason');
        });
    }

    public function down(): void
    {
        Schema::table('board_sessions', function (Blueprint $table) {
            $table->dropColumn(['advisor_failures', 'failure_reason', 'active_advisor_ids']);
        });
    }
};