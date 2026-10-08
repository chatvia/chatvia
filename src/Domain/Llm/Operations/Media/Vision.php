<?php

namespace Domain\Llm\Operations\Media;

use Domain\Llm\Contracts\LlmProviderInterface;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Laravel\Facades\Image;

class Vision
{
    public function __construct(
        public LlmProviderInterface $provider,
    ) {}

    public function describeImage(string $image): bool|string
    {
        if (file_exists($image)) {
            $image = Image::decodePath($image)->encode(new JpegEncoder)->toDataUri()->toString();
        }

        $prompt = prompt('image/describe');

        $response = $this->provider->completion([
            'model' => config('chatvia.ai.models.vision'),
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
        $imageDataUri = Image::decode($imageContent)->encode(new JpegEncoder)->toDataUri()->toString();

        return $this->describeImage($imageDataUri);
    }
}
