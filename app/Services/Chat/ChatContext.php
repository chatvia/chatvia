<?php

namespace App\Services\Chat;

use App\Data\Chat\ChatInputPayload;
use App\Services\Chat\Data\StreamRequestData;
use Illuminate\Support\Collection;

class ChatContext
{
    public string $streamedMessage = '';

    public ?string $generationId = null;

    public ?int $tokenCount = 0;

    public Collection $sources;

    public ?float $confidenceScore = null;

    public ?object $qaMatch = null;

    private float $startTime;

    private float $endTime;

    public function __construct(
        public readonly StreamRequestData $requestData,
        public readonly ChatInputPayload $payload,
        public readonly array $llmMessages,
        public readonly ?string $userMessageId,
        public readonly string $assistantMessageId,
        public readonly string $queryLanguage,
    ) {
        $this->sources = collect();
        $this->startTime = microtime(true);
    }

    public function appendStreamedMessage(string $content): void
    {
        $this->streamedMessage .= $content;
    }

    public function setGenerationData(?string $generationId, ?int $tokenCount): void
    {
        $this->generationId = $generationId;
        $this->tokenCount = $tokenCount;
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
        $this->endTime = microtime(true);
    }

    public function getResponseTimeMs(): int
    {
        return (int) round(($this->endTime - $this->startTime) * 1000);
    }
}
