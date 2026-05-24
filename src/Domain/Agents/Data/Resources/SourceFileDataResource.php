<?php

namespace Domain\Agents\Data\Resources;

use Domain\Sources\Files\Models\SourceFile;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class SourceFileDataResource extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $type,
        public int $size_in_kb,
        public string $mime_type,
        public string $status,
        public int $length,
        public int $progress,
        public int $agents_count,
        public CarbonInterface $created_at,
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
        );
    }

    public static function fromFiles(Collection $files): \Illuminate\Support\Collection
    {
        return collect($files)->map(function (SourceFile $file) {
            return self::fromFile($file);
        });
    }
}
