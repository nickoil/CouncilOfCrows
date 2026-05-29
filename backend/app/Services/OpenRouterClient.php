<?php

namespace App\Services;

use OpenAI\Client as OpenAIClient;

class OpenRouterClient
{
    public function __construct(private readonly OpenAIClient $client) {}

    public function createChat(array $payload): mixed
    {
        return $this->client->chat()->create($payload);
    }
}