<?php

namespace Database\Seeders\Demo;

/**
 * The numbers behind one demo AI reply: tokens, response time, whether it
 * found an answer and how sure it was. The chats and the analytics history
 * both build their replies here, so the token, latency and answer-rate
 * charts look the same for old and recent days.
 *
 * Prompt tokens are high because every turn sends the agent's instructions,
 * the retrieved knowledge and the chat so far; the response time follows
 * the length of the answer.
 */
final readonly class DemoReply
{
    private function __construct(
        public int $promptTokens,
        public int $completionTokens,
        public int $responseTimeMs,
        public bool $foundAnswer,
        public float $confidence,
    ) {}

    /** A reply grounded in the knowledge base. */
    public static function answered(): self
    {
        return self::make(foundAnswer: true, confidence: DemoRandom::between(0.71, 0.97));
    }

    /** A reply that had to say the knowledge base does not cover the question. */
    public static function unanswered(): self
    {
        return self::make(foundAnswer: false, confidence: DemoRandom::between(0.18, 0.45));
    }

    private static function make(bool $foundAnswer, float $confidence): self
    {
        $completion = $foundAnswer ? random_int(70, 420) : random_int(40, 110);

        return new self(
            promptTokens: random_int(1_600, 4_200),
            completionTokens: $completion,
            responseTimeMs: min(15_000, 700 + ($completion * 14) + random_int(0, 1_400)),
            foundAnswer: $foundAnswer,
            confidence: $confidence,
        );
    }
}
