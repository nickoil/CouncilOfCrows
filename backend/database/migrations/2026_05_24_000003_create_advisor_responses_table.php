<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('advisor_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_session_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->string('model_used');
            $table->integer('prompt_tokens')->default(0);
            $table->integer('completion_tokens')->default(0);
            $table->decimal('cost_gbp', 10, 6)->default(0);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('advisor_responses');
    }
};