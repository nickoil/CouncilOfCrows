<?php
return [
    'api_key'       => env('OPENROUTER_API_KEY', ''),
    'base_uri'      => env('OPENROUTER_BASE_URI', 'https://openrouter.ai/api/v1'),
    'default_model' => env('OPENROUTER_DEFAULT_MODEL', 'google/gemini-2.5-flash-preview'),
];