<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\File\Actions;

use Domain\Guardrail\Enums\PiiFilterType;
use Domain\Guardrail\Services\Detectors\PiiDetector;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\File\Models\SourceFile;

/**
 * Looks through a trained file for identifiers a knowledge base should almost
 * never hold, card numbers, IBANs and US Social Security Numbers, and notes in
 * the file's metadata which kinds it found and in how many parts, so the
 * files list can warn the owner. The text is never changed: whether the file
 * may stay is the owner's call. E-mail addresses and phone numbers are left
 * out on purpose, since support content is full of them.
 */
class FlagPersonalDataInFileAction
{
    /** The identifiers worth a warning; all are checksum- or format-checked. */
    public const array TYPES = [PiiFilterType::CreditCard, PiiFilterType::Iban, PiiFilterType::UsSsn];

    private const string KEY = 'personal_data';

    public function __construct(
        private readonly PiiDetector $detector,
    ) {}

    public function execute(SourceFile $file): void
    {
        $only = array_map(static fn (PiiFilterType $type): string => $type->value, self::TYPES);
        $types = [];
        $parts = 0;

        Chunk::query()
            ->where('chunkable_type', $file->getMorphClass())
            ->where('chunkable_id', $file->id)
            ->select(['id', 'chunk'])
            ->lazyById(200)
            ->each(function (Chunk $chunk) use ($only, &$types, &$parts): void {
                $found = $this->detector->redact($chunk->chunk, $only)->types;

                if ($found !== []) {
                    $parts++;
                    $types = [...$types, ...$found];
                }
            });

        $metadata = (array) ($file->metadata ?? []);
        unset($metadata[self::KEY]);

        if ($parts > 0) {
            $metadata[self::KEY] = ['types' => array_values(array_unique($types)), 'parts' => $parts];
        }

        $file->update(['metadata' => $metadata]);
    }
}
