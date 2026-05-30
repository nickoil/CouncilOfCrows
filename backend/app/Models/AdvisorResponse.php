<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvisorResponse extends Model {
    protected $fillable = ['board_session_id','advisor_id','response_type','round_number','tension_key','tension_label','content','model_used',
                           'prompt_tokens','completion_tokens','cost_gbp'];
    public function boardSession(): BelongsTo {
        return $this->belongsTo(BoardSession::class);
    }
    public function advisor(): BelongsTo {
        return $this->belongsTo(Advisor::class);
    }
}