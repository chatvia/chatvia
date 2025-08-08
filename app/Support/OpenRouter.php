<?php

namespace App\Support;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;

class OpenRouter
{
    public function stream($model, $llmMessages, array $settings = []): ResponseInterface
    {
        $client = app(Client::class);

        return $client->post(config('services.openrouter.completion_endpoint'), [
            'headers' => [
                'Authorization' => 'Bearer '.config('services.openrouter.api_key'),
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $model.':nitro',
                'messages' => $llmMessages,
                'stream' => true,
                //                ...$options,
            ],
            'max_tokens' => '2000',

            'stream' => true,
            //            'provider' => [
            //                'sort' => 'latency', // https://openrouter.ai/docs/features/provider-routing
            //            ],
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
