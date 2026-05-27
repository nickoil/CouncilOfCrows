<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('board_sessions', function (Blueprint $table) {
            $table->id();
            $table->text('question');
            $table->string('status')->default('processing'); // processing|complete|failed
            $table->string('depth')->default('standard');    // quick|standard|deep
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('board_sessions');
    }
};