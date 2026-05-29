<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoardSession extends Model {
    protected $fillable = ['question', 'status', 'depth', 'consensus', 'advisor_failures', 'failure_reason', 'active_advisor_ids'];

    protected $casts = [
        'advisor_failures' => 'array',
        'active_advisor_ids' => 'array',
    ];

    public function advisorResponses(): HasMany {
        return $this->hasMany(AdvisorResponse::class);
    }
}