<?php

namespace App\Services\Infrastructure\LLM\Operations;

use App\Contracts\LLMProviderInterface;
use App\Services\Prompts\PromptLoader;
use Intervention\Image\Laravel\Facades\Image;

class Vision
{
    public function __construct(
        public LLMProviderInterface $provider,
    ) {}

    public function describeImage(string $image)
    {
        if (file_exists($image)) {
            $image = Image::read(file_get_contents($image))->toJpeg()->toDataUri();
        }

        $prompt = PromptLoader::load('image/describe');

        $response = $this->provider->completion([
            'model' => config('ai.models.vision'),
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $prompt,
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => $image,
                            ],
                        ],
                    ],
                ],
            ],
        ])->json();

        return $response['choices'][0]['message']['content'] ?? '0';
    }
}
