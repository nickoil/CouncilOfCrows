<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('board_sessions', function (Blueprint $table) {
            $table->string('deliberation_mode')->default('single_round')->after('depth');
            $table->json('selected_tensions')->nullable()->after('active_advisor_ids');
        });

        Schema::table('advisor_responses', function (Blueprint $table) {
            $table->string('response_type')->default('independent')->after('advisor_id');
            $table->unsignedSmallInteger('round_number')->default(1)->after('response_type');
            $table->string('tension_key')->nullable()->after('round_number');
            $table->string('tension_label')->nullable()->after('tension_key');
        });
    }

    public function down(): void
    {
        Schema::table('board_sessions', function (Blueprint $table) {
            $table->dropColumn(['deliberation_mode', 'selected_tensions']);
        });

        Schema::table('advisor_responses', function (Blueprint $table) {
            $table->dropColumn(['response_type', 'round_number', 'tension_key', 'tension_label']);
        });
    }
};