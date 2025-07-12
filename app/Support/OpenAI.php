<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

class OpenAI
{
    public static function completion(array $data)
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.config('services.openai.api_key'),
            'Content-Type' => 'application/json',
        ])
            ->timeout(600)
            ->post(config('services.openai.completion_endpoint'), $data)
            ->throw()
            ->json('choices.0.message.content');
    }
}
