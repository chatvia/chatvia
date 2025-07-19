<?php

namespace App\Support\Providers;

use Illuminate\Support\Facades\Http;

class Groq
{
    public static function completion(array $data)
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.config('services.groq.api_key'),
            'Content-Type' => 'application/json',
        ])
            ->timeout(600)
            ->post(config('services.grok.completion_endpoint'), $data)
            ->throw();
    }
}
