<?php

namespace Domain\Agent\Data\Resources\Sources;

use Spatie\LaravelData\Data;

/**
 * A trained knowledge source with the agents it is attached to, for the
 * "link an existing source" picker.
 */
class AgentSourcesListData extends Data
{
    public function __construct(
        public int $id,
        public string $filename,
        public string $filetype,
        public string $length,
        public array $agents = [],
    ) {}

    /**
     * @return AgentSourcesListData[]
     */
    public static function fromSources($sources): array
    {
        $data = [];
        foreach ($sources as $source) {
            $data[] = new self(
                id: $source->id,
                filename: $source->filename,
                filetype: $source->filetype,
                length: $source->length,
                agents: $source->agents->map(fn ($agent): array => [
                    'id' => $agent->id,
                    'name' => $agent->name,
                ])->toArray(),
            );
        }

        return $data;
    }
}
