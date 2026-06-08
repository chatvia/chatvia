<?php

namespace Domain\Guardrail\Data;

use Spatie\LaravelData\Data;

/**
 * The per-agent guardrail configuration (the `guardrails` model-settings group).
 * Built from the SaveGuardrailSettingsRequest; `toSettings()` is the key→value
 * map persisted via Agent::setSetting.
 */
class GuardrailSettingsData extends Data
{
    /**
     * @param  list<string>  $blocked_topics
     */
    public function __construct(
        public bool $enabled,
        public bool $pii,
        public bool $injection,
        public bool $toxicity,
        public array $blocked_topics,
        public ?string $allowed_scope,
        public ?string $refusal_message,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toSettings(): array
    {
        return [
            'enabled' => $this->enabled,
            'pii' => $this->pii,
            'injection' => $this->injection,
            'toxicity' => $this->toxicity,
            'blocked_topics' => $this->blocked_topics,
            'allowed_scope' => $this->allowed_scope ?? '',
            'refusal_message' => $this->refusal_message ?? '',
        ];
    }
}
