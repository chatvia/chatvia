<?php

namespace App\Support;

use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;

class OpenRouter
{
    public function stream($model, array $messages, array $settings = []): ResponseInterface
    {
        return app(GuzzleClient::class)->post(config('services.openrouter.completion_endpoint'), [
            'headers' => [
                'Authorization' => 'Bearer '.config('services.openrouter.api_key'),
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $model.':nitro',
                'messages' => $messages,
                'stream' => true,
            ],

            'stream' => true,
            ...$settings,
        ]);
    }

    public static function completion(array $data)
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.config('services.openrouter.api_key'),
            'Content-Type' => 'application/json',
        ])
            ->timeout(600)
            ->post(config('services.openrouter.completion_endpoint'), $data)
            ->throw();
    }

    public function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.config('services.openrouter.api_key'),
            'Content-Type' => 'application/json',
        ];
    }
}
