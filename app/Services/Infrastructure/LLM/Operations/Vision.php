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

    public function describeImage(string $image): bool|string
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

        $result = $response['choices'][0]['message']['content'] ?? 'no_info';

        if (str_contains($result, 'no_info')) {
            return false;
        }

        return $result;
    }

    public function fromDataUrl(string $imageContent): bool|string
    {
        $imageDataUri = Image::read($imageContent)->toJpeg()->toDataUri();

        return $this->describeImage($imageDataUri);
    }
}
