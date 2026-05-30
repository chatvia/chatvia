<?php

namespace Domain\Agents\Data;

use Spatie\LaravelData\Data;

class UpdateTemplateData extends Data
{
    /**
     * @param  array<string, mixed>|null  $hostedChatConfig
     * @param  array<string, mixed>|null  $widgetConfig
     * @param  array<string, mixed>|null  $barConfig
     */
    public function __construct(
        public string $name,
        public ?int $agentPromptId = null,
        public bool $isPublic = false,
        public ?array $hostedChatConfig = null,
        public ?array $widgetConfig = null,
        public ?array $barConfig = null,
    ) {}
}
