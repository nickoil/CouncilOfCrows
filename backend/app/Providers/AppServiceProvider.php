<?php

namespace App\Providers;

use App\Support\AdvisorCatalog;
use Illuminate\Support\ServiceProvider;
use OpenAI\Client as OpenAIClient;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OpenAIClient::class, function () {
            return \OpenAI::factory()
                ->withApiKey(config('openrouter.api_key'))
                ->withBaseUri(config('openrouter.base_uri'))
                ->withHttpHeader('HTTP-Referer', config('openrouter.site_url'))
                ->withHttpHeader('X-OpenRouter-Title', config('openrouter.site_name'))
                ->make();
        });
    }

    public function boot(): void
    {
        AdvisorCatalog::ensureDefaults();
    }
}
