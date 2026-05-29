<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('advisors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('role')->unique();          // strategist, sceptic, synthesiser …
            $table->text('description')->nullable();
            $table->text('system_prompt');
            $table->string('model')->nullable();       // override; falls back to default_model
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('advisors');
    }
};
