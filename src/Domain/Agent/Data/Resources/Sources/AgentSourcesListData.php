<?php

namespace Domain\Agent\Data\Resources\Sources;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Enums\KnowledgeFileType;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Database\Eloquent\Collection;
use Spatie\LaravelData\Data;

/**
 * A trained knowledge source with the agents it is attached to, for the
 * "link an existing source" picker.
 */
class AgentSourcesListData extends Data
{
    /**
     * @param  list<array{id: int, name: string}>  $agents
     */
    public function __construct(
        public int $id,
        public string $filename,
        public KnowledgeFileType $filetype,
        public string $length,
        public array $agents = [],
    ) {}

    /**
     * @param  Collection<int, SourceFile>  $sources  files with their `agents` relation loaded
     * @return list<AgentSourcesListData>
     */
    public static function fromSources(Collection $sources): array
    {
        $data = [];
        foreach ($sources as $source) {
            $data[] = new self(
                id: $source->id,
                filename: $source->filename,
                filetype: $source->filetype,
                length: (string) $source->length,
                agents: $source->agents->map(fn (Agent $agent): array => [
                    'id' => $agent->id,
                    'name' => $agent->name,
                ])->values()->all(),
            );
        }

        return $data;
    }
}
