<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Data\DataExport;

use App\Identity\Workspace\Models\DataExport;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The signed-in user's current data export as the GDPR export page shows it.
 * `download_url` is only set while the ZIP is ready and not yet expired.
 */
#[TypeScript]
class DataExportResourceData extends Data
{
    public function __construct(
        public int $id,
        public string $status,
        public ?int $file_size,
        public ?string $expires_at,
        public ?string $error_message,
        public ?string $created_at,
        public ?string $download_url,
    ) {}

    public static function fromModel(DataExport $export): self
    {
        return new self(
            id: $export->id,
            status: $export->status->value,
            file_size: $export->file_size,
            expires_at: $export->expires_at?->toIso8601String(),
            error_message: $export->error_message,
            created_at: $export->created_at?->toIso8601String(),
            download_url: $export->isReady() && ! $export->isExpired()
                ? route('account.data-export.download', $export)
                : null,
        );
    }
}
