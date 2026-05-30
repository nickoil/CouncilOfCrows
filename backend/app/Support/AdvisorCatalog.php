<?php

namespace App\Support;

use App\Models\Advisor;
use Database\Seeders\AdvisorSeeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AdvisorCatalog
{
    public static function ensureDefaults(): void
    {
        if (app()->environment('testing') || ! Schema::hasTable('advisors')) {
            return;
        }

        $hasChair = Advisor::where('role', 'chair')->exists();
        $hasCouncil = Advisor::where('active', true)
            ->where('role', '!=', 'chair')
            ->exists();

        if ($hasChair && $hasCouncil) {
            return;
        }

        Log::warning('[Council] Advisor catalog missing; restoring default advisors.', [
            'has_chair' => $hasChair,
            'has_council' => $hasCouncil,
        ]);

        app(AdvisorSeeder::class)->run();
    }
}