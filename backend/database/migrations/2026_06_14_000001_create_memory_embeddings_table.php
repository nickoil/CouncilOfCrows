<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memory_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('board_session_id')->constrained()->cascadeOnDelete();
            $table->string('content_type', 50)->default('deliberation');
            $table->text('content');
            $table->string('model');
            $table->timestamps();
        });

        $dimensions = (int) config('council.embedding.dimensions', 1536);
        DB::statement("ALTER TABLE memory_embeddings ADD COLUMN embedding vector({$dimensions}) NOT NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('memory_embeddings');
    }
};
