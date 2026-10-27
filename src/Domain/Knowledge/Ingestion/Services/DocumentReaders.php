<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Services;

use Domain\Llm\Enums\AiCapability;
use Domain\Llm\Enums\AiProvider;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Which service reads uploaded documents (`chatvia.ai.document_parser`):
 * Mistral OCR, Azure Document Intelligence, or none. "Automatic" is the
 * first one connected. Without a reader, PDF, Word, slides and images
 * cannot be uploaded (KnowledgeFileType::enabled()); spreadsheets
 * and CSV are still read by the app itself.
 *
 * Each reader gets only the formats it documents. Other images are turned
 * into PNG for it first; other files (old office formats, RTF, Excel, CSV)
 * are read by the app, as the reader would fail on them or, for Excel with
 * Azure, lose the tables.
 */
final class DocumentReaders
{
    /** The drivers `chatvia.ai.document_parser` accepts besides `auto`. */
    public const array DRIVERS = ['mistral', 'azure', 'none'];

    /** What Mistral OCR documents: PDF, Word, PowerPoint and the common images. */
    public const array MISTRAL_READS = ['pdf', 'docx', 'pptx', 'png', 'jpg', 'jpeg', 'avif', 'tif', 'tiff', 'bmp', 'gif', 'webp'];

    /** What Azure's layout model documents (HEIC is a HEIF file), without Excel: Azure reads no tables in it. */
    public const array AZURE_READS = ['pdf', 'docx', 'pptx', 'jpg', 'jpeg', 'png', 'bmp', 'tif', 'tiff', 'heif', 'heic'];

    /** Whether a reader takes a file of this extension as it is. */
    public static function readsItself(string $driver, string $extension): bool
    {
        return collect(match ($driver) {
            'mistral' => self::MISTRAL_READS,
            'azure' => self::AZURE_READS,
            default => [],
        })->containsStrict(Str::lower($extension));
    }

    /** The driver in use, with "Automatic" resolved. */
    public function driver(): string
    {
        $driver = Str::lower(Str::trim((string) config('chatvia.ai.document_parser', 'auto')));

        if ($driver === '' || $driver === 'auto') {
            return AiProvider::firstConnectedFor(AiCapability::Documents)?->value ?? 'none';
        }

        if (! collect(self::DRIVERS)->containsStrict($driver)) {
            throw new InvalidArgumentException('Unknown chatvia.ai.document_parser "'.$driver.'". Use auto, '.Arr::join(self::DRIVERS, ', ').'.');
        }

        return $driver;
    }

    /** Whether a reader is set up and connected, so PDF, Word, slides and images can be read. */
    public function available(): bool
    {
        $provider = AiProvider::tryFrom($this->driver());

        return $provider instanceof AiProvider && $provider->isConnected();
    }
}
