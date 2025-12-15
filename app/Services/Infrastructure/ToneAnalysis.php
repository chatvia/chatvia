<?php

namespace App\Services\Infrastructure;

use App\Contracts\LLMProviderInterface;
use App\Enums\SentimentEnum;
use App\Enums\ToneEnum;
use App\Services\Infrastructure\LLM\OpenAI;

class ToneAnalysis
{
    public function __construct(
        public LLMProviderInterface $provider
    )
    {
    }

    public function analyze(string $text): array
    {
        $response = $this->provider->completion([
            'model' => 'llama-3.1-8b-instruct',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '
                    You are an expert in sentiment and tone analysis for customer support messages.
                    Analyze the given text and respond with TWO things:
                    1. Sentiment: Positive, Negative, or Neutral
                    2. Tone: Neutral, Frustrated, Urgent, Confused, or Satisfied

                    Respond in this exact format:
                    Sentiment: [sentiment]
                    Tone: [tone]
                ',
                ],
                [
                    'role' => 'user',
                    'content' => "Analyze the sentiment and tone of the following text:\n\n\"{$text}\"",
                ],
            ],
        ]);

        $response = strtolower($response);

        // Parse sentiment
        $sentiment = SentimentEnum::NEUTRAL;
        if (str_contains($response, 'sentiment:')) {
            if (str_contains($response, 'positive')) {
                $sentiment = SentimentEnum::POSITIVE;
            } elseif (str_contains($response, 'negative')) {
                $sentiment = SentimentEnum::NEGATIVE;
            }
        }

        // Parse tone
        $tone = ToneEnum::NEUTRAL;
        if (str_contains($response, 'tone:')) {
            if (str_contains($response, 'frustrated')) {
                $tone = ToneEnum::FRUSTRATED;
            } elseif (str_contains($response, 'urgent')) {
                $tone = ToneEnum::URGENT;
            } elseif (str_contains($response, 'confused')) {
                $tone = ToneEnum::CONFUSED;
            } elseif (str_contains($response, 'satisfied')) {
                $tone = ToneEnum::SATISFIED;
            }
        }

        return [
            'sentiment' => $sentiment,
            'tone' => $tone,
        ];
    }
}
