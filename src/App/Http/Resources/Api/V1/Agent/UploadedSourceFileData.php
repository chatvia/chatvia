<?php

namespace App\Http\Resources\Api\V1\Agent;

use Domain\Knowledge\Source\File\Models\SourceFile;
use Spatie\LaravelData\Data;

/**
 * Compact projection returned after a successful knowledge-source upload.
 */
class UploadedSourceFileData extends Data
{
    public function __construct(
        public int $id,
        public string $kind,
        public string $name,
        public string $mime_type,
        public int $size_kb,
        public string $status,
    ) {}

    public static function fromModel(SourceFile $file): self
    {
        return new self(
            id: $file->id,
            kind: 'file',
            name: $file->filename,
            mime_type: $file->mime_type,
            size_kb: $file->size_in_kb,
            status: (string) $file->status,
        );
    }
}
