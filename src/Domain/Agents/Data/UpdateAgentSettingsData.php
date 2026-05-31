<?php

namespace Domain\Agents\Data;

use Domain\Agents\Models\Agent;
use Spatie\LaravelData\Data;

/**
 * Inputs for `UpdateAgentSettingsGroupAction::execute()`. The `payload` shape
 * is dynamic — it varies per `group` and is filtered against
 * `config/model-settings.php` inside the action — so it stays as an
 * untyped associative array on purpose.
 *
 * @phpstan-type SettingsPayload array<string, mixed>
 */
class UpdateAgentSettingsData extends Data
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public Agent $agent,
        public string $group,
        public array $payload,
    ) {}
}
