<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoardSession extends Model {
    protected $fillable = ['question', 'subject', 'status', 'depth', 'deliberation_mode', 'consensus', 'advisor_failures', 'failure_reason', 'active_advisor_ids', 'selected_tensions', 'cost_summary', 'memory_context', 'retrieved_session_ids'];

    protected $casts = [
        'advisor_failures'     => 'array',
        'active_advisor_ids'   => 'array',
        'selected_tensions'    => 'array',
        'cost_summary'         => 'array',
        'retrieved_session_ids' => 'array',
    ];

    public function advisorResponses(): HasMany {
        return $this->hasMany(AdvisorResponse::class);
    }
}