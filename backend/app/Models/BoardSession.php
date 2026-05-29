<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BoardSession extends Model {
    protected $fillable = ['question', 'status', 'depth', 'consensus'];
    public function advisorResponses(): HasMany {
        return $this->hasMany(AdvisorResponse::class);
    }
}