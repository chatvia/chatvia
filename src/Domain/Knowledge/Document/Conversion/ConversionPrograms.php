<?php

declare(strict_types=1);

namespace Domain\Knowledge\Document\Conversion;

use Domain\Knowledge\Ingestion\Services\DocumentReaders;
use Support\Executable;

/**
 * Which program a document format needs on this server, and whether it is
 * missing: pandoc for RTF (and DOCX when no document reader reads it),
 * catdoc and catppt for the old Word and PowerPoint files, unless the
 * document reader in use reads the format itself. The ebooks need none: the
 * app reads them itself when pandoc is missing ({@see EbookText}). A file
 * whose program is missing could be uploaded but never trained, so the
 * upload refuses it and the picker leaves it out.
 */
final class ConversionPrograms
{
    /** The reader in use, read once per instance. */
    private ?string $driver = null;

    public function __construct(
        private readonly DocumentReaders $readers,
        private readonly OfficeText $officeText,
    ) {}

    /** The program (its `services.bin` key) the format needs and this server lacks, or null. */
    public function missingFor(string $extension): ?string
    {
        $program = $this->programFor($extension);

        return $program !== null && ! Executable::exists((string) config("services.bin.{$program}")) ? $program : null;
    }

    /** The program the format needs with the reader in use, or null when none does. */
    public function programFor(string $extension): ?string
    {
        $extension = strtolower($extension);

        if (DocumentReaders::readsItself($this->driver(), $extension)) {
            return null;
        }

        return $this->officeText->programFor($extension);
    }

    private function driver(): string
    {
        return $this->driver ??= $this->readers->available() ? $this->readers->driver() : 'none';
    }
}
