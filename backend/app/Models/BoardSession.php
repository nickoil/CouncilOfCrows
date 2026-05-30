<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoardSession extends Model {
    protected $fillable = ['question', 'status', 'depth', 'deliberation_mode', 'consensus', 'advisor_failures', 'failure_reason', 'active_advisor_ids', 'selected_tensions'];

    protected $casts = [
        'advisor_failures' => 'array',
        'active_advisor_ids' => 'array',
        'selected_tensions' => 'array',
    ];

    public function advisorResponses(): HasMany {
        return $this->hasMany(AdvisorResponse::class);
    }
}