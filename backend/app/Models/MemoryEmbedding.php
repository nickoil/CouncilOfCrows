<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemoryEmbedding extends Model
{
    protected $fillable = [
        'board_session_id',
        'content_type',
        'content',
        'model',
    ];

    public function boardSession(): BelongsTo
    {
        return $this->belongsTo(BoardSession::class);
    }
}
