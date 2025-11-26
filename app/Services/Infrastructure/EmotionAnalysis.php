<?php

namespace App\Services\Infrastructure;

use App\Enums\EmotionEnum;
use App\Enums\SentimentEnum;
use App\Services\Infrastructure\LLM\OpenAI;

class EmotionAnalysis
{
    public function analyze(string $text): array
    {
        $response = OpenAI::completion([
            'model' => 'gpt-5-nano',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '
                    You are an expert in sentiment and emotion analysis.
                    Analyze the given text and respond with TWO things:
                    1. Sentiment: Positive, Negative, or Neutral
                    2. Emotion: Happy, Neutral, Frustrated, Confused, Angry, Sad, Anxious, Excited, or Satisfied
                    
                    Respond in this exact format:
                    Sentiment: [sentiment]
                    Emotion: [emotion]
                ',
                ],
                [
                    'role' => 'user',
                    'content' => "Analyze the sentiment and emotion of the following text:\n\n\"{$text}\"",
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

        // Parse emotion
        $emotion = EmotionEnum::NEUTRAL;
        if (str_contains($response, 'emotion:')) {
            if (str_contains($response, 'happy')) {
                $emotion = EmotionEnum::HAPPY;
            } elseif (str_contains($response, 'frustrated')) {
                $emotion = EmotionEnum::FRUSTRATED;
            } elseif (str_contains($response, 'confused')) {
                $emotion = EmotionEnum::CONFUSED;
            } elseif (str_contains($response, 'angry')) {
                $emotion = EmotionEnum::ANGRY;
            } elseif (str_contains($response, 'sad')) {
                $emotion = EmotionEnum::SAD;
            } elseif (str_contains($response, 'anxious')) {
                $emotion = EmotionEnum::ANXIOUS;
            } elseif (str_contains($response, 'excited')) {
                $emotion = EmotionEnum::EXCITED;
            } elseif (str_contains($response, 'satisfied')) {
                $emotion = EmotionEnum::SATISFIED;
            }
        }

        return [
            'sentiment' => $sentiment,
            'emotion' => $emotion,
        ];
    }
}
