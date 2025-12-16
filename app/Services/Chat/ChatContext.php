<?php

namespace App\Services\Chat;

use App\Data\Chat\ChatInputPayload;
use App\Services\Chat\Data\StreamRequestData;
use Illuminate\Support\Collection;

class ChatContext
{
    public string $streamedMessage = '';

    public ?string $generationId = null;

    public Collection $sources;

    public ?float $confidenceScore = null;

    public ?object $qaMatch = null;

    private float $startTime;

    private ?float $endTime = null;

    public function __construct(
        public readonly StreamRequestData $requestData,
        public readonly ChatInputPayload $payload,
        public readonly array $llmMessages,
        public readonly ?string $userMessageId,
        public readonly string $assistantMessageId,
        public readonly ?string $queryLanguage = null,
        ?float $startTime = null,
    ) {
        $this->sources = collect();
        $this->startTime = $startTime ?? microtime(true);
    }

    public function getStartTime(): float
    {
        return $this->startTime;
    }

    public function appendStreamedMessage(string $content): void
    {
        $this->streamedMessage .= $content;
    }

    public function setSources(Collection $sources): void
    {
        $this->sources = $sources;
    }

    public function setConfidenceScore(?float $confidenceScore): void
    {
        $this->confidenceScore = $confidenceScore;
    }

    public function setQAMatch(?object $qaMatch): void
    {
        $this->qaMatch = $qaMatch;
    }

    public function hasQAMatch(): bool
    {
        return $this->qaMatch !== null;
    }

    public function markEndTime(): void
    {
        // Only set endTime once (first call wins)
        if ($this->endTime === null) {
            $this->endTime = microtime(true);
        }
    }

    public function getResponseTimeMs(): int
    {
        // If endTime hasn't been set yet, use current time
        $endTime = $this->endTime ?? microtime(true);

        return (int) round(($endTime - $this->startTime) * 1000);
    }
}
