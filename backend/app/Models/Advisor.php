<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Advisor extends Model
{
    protected $fillable = [
        'name',
        'role',
        'description',
        'system_prompt',
        'model',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function responses(): HasMany
    {
        return $this->hasMany(AdvisorResponse::class);
    }
}
