<?php

namespace Domain\Agent\Data\Resources\Sources;

use Carbon\CarbonInterface;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Enums\KnowledgeFileType;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A knowledge file as the Knowledge page lists it: name, type, size, training
 * status and progress, how many agents use it, and any card numbers or
 * similar identifiers found in it.
 */
#[TypeScript]
class SourceFileDataResource extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public KnowledgeFileType $type,
        public int $size_in_kb,
        public string $mime_type,
        public SourceStatus $status,
        public int $length,
        public int $progress,
        public int $agents_count,
        public CarbonInterface $created_at,
        /**
         * Card numbers, IBANs or US SSNs found in the trained file (filter
         * keys), and in how many of its parts; empty when none.
         *
         * @var list<string>
         */
        public array $personal_data = [],
        public int $personal_data_parts = 0,
    ) {}

    public static function fromFile(SourceFile $file): self
    {
        return new self(
            id: $file->id,
            name: $file->filename,
            type: $file->filetype,
            size_in_kb: $file->size_in_kb,
            mime_type: $file->mime_type,
            status: $file->status,
            length: $file->length,
            progress: $file->ingestionProgress(),
            agents_count: $file->agents_count ?? 0,
            created_at: $file->created_at,
            personal_data: array_values((array) ($file->metadata['personal_data']['types'] ?? [])),
            personal_data_parts: (int) ($file->metadata['personal_data']['parts'] ?? 0),
        );
    }
}
