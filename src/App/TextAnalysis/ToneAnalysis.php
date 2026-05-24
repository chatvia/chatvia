<?php

namespace App\TextAnalysis;

use App\LLM\Contracts\LLMProviderInterface;
use Domain\Analytics\Enums\SentimentEnum;
use Domain\Analytics\Enums\ToneEnum;

class ToneAnalysis
{
    public function __construct(
        public LLMProviderInterface $provider
    ) {}

    public function analyze(string $text): array
    {
        $response = $this->provider->completion([
            'model' => config('ai.models.tone'),
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '
                    You are an expert in sentiment and tone analysis for customer support messages.
                    Analyze the given text and respond with TWO things:
                    1. Sentiment: Positive, Negative, or Neutral
                    2. Tone: Neutral, Frustrated, Urgent, Confused, or Satisfied

                    TONE DEFINITIONS:
                    - Neutral: Calm, straightforward questions or statements. Simple inquiries about features, pricing, or information.
                    - Frustrated: Shows annoyance, disappointment, or irritation. May include complaints or expressions of dissatisfaction.
                    - Urgent: Time-sensitive requests, critical issues, or immediate need for assistance. Often uses words like "urgent", "asap", "immediately".
                    - Confused: Genuinely uncertain or lost. Multiple questions showing they don\'t understand how things work, asking for clarification after unclear responses, or expressing they are "lost" or "don\'t understand".
                    - Satisfied: Happy with service, expressing thanks or positive feedback.

                    IMPORTANT: 
                    - A simple question like "How do I do X?" is NEUTRAL, not confused.
                    - "What is your pricing?" is NEUTRAL, not confused.
                    - Only mark as Confused if the user explicitly shows they are lost, uncertain, or struggling to understand after multiple attempts.
                    - Questions are the foundation of support - they are NEUTRAL by default unless they show genuine confusion or uncertainty.

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
