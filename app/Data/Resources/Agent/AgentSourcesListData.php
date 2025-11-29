<?php

namespace App\Data\Resources\Agent;

use Spatie\LaravelData\Data;

class AgentSourcesListData extends Data
{
    public function __construct(
        public int $id,
        public string $filename,
        public string $filetype,
        public string $length,
        public array $agents = [],
    ) {}

    public static function fromSources($sources)
    {
        $data = [];
        foreach ($sources as $source) {
            $data[] = new self(
                id: $source->id,
                filename: $source->filename,
                filetype: $source->filetype,
                length: $source->length,
                agents: $source->agents->map(function ($agent) {
                    return [
                        'id' => $agent->id,
                        'name' => $agent->name,
                    ];
                })->toArray(),
            );
        }

        return $data;
    }
}
