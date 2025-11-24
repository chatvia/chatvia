<?php

namespace App\Services\Infrastructure;

use App\Enums\SentimentEnum;
use App\Services\Infrastructure\LLM\OpenAI;

class SentimentAnalysis
{
    public function analyze(string $text): SentimentEnum
    {
        $response = OpenAI::completion([
            'model' => 'gpt-5-nano',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are sentiment analysis expert. Analyze the sentiment of the given text and respond with one of the following labels: Positive, Negative, Neutral.',
                ],
                [
                    'role' => 'user',
                    'content' => "Analyze the sentiment of the following text:\n\n\"{$text}\"",
                ],
            ],
        ]);

        return match ($response) {
            str_contains($response, 'Positive') => SentimentEnum::POSITIVE,
            str_contains($response, 'Negative') => SentimentEnum::NEGATIVE,
            default => SentimentEnum::NEUTRAL,
        };
    }
}
