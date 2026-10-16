<?php

declare(strict_types=1);

namespace Domain\Form\Data;

use Spatie\LaravelData\Data;

/**
 * An agent's lead-capture field settings as the owner saves them.
 */
class UpdateLeadSettingsData extends Data
{
    /**
     * @param  list<LeadFieldData>  $fields
     */
    public function __construct(
        public array $fields,
    ) {}

    /**
     * The fields as the agent's `leads` settings store them.
     *
     * @return list<array<string, mixed>>
     */
    public function toSettings(): array
    {
        return array_map(static fn (LeadFieldData $field): array => $field->toArray(), $this->fields);
    }
}
