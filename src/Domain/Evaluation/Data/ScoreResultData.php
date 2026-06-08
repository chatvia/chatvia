<?php

namespace Domain\Evaluation\Data;

/**
 * The outcome of scoring one eval case: a binary pass, an optional 0..1 score,
 * and a short human rationale.
 */
final readonly class ScoreResultData
{
    public function __construct(
        public bool $pass,
        public ?float $score = null,
        public ?string $rationale = null,
    ) {}

    public static function pass(?float $score = 1.0, ?string $rationale = null): self
    {
        return new self(true, $score, $rationale);
    }

    public static function fail(?float $score = 0.0, ?string $rationale = null): self
    {
        return new self(false, $score, $rationale);
    }
}
