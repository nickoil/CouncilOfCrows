<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvisorResponse extends Model {
    protected $fillable = ['board_session_id','content','model_used',
                           'prompt_tokens','completion_tokens','cost_gbp'];
    public function boardSession(): BelongsTo {
        return $this->belongsTo(BoardSession::class);
    }
}