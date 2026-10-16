<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Agent;

use Carbon\CarbonInterface;
use Domain\Agent\Prompt\Models\AgentPrompt;
use Spatie\LaravelData\Data;

/**
 * A reusable prompt from the workspace library. Pass `id` as `prompt_id`
 * when creating or updating an agent to attach it.
 */
class PromptData extends Data
{
    public function __construct(
        /**
         * The prompt's ID.
         */
        public string $id,
        /**
         * The prompt's name.
         */
        public string $name,
        /**
         * The prompt text.
         */
        public string $prompt,
        /**
         * When the prompt was created.
         */
        public ?CarbonInterface $created_at,
    ) {}

    public static function fromModel(AgentPrompt $prompt): self
    {
        return new self(
            id: $prompt->external_id,
            name: $prompt->name,
            prompt: $prompt->prompt,
            created_at: $prompt->created_at,
        );
    }
}
