<?php

declare(strict_types=1);

namespace Domain\Knowledge\Document\Conversion;

use Domain\Knowledge\Ingestion\Services\DocumentReaders;
use Illuminate\Support\Str;
use Support\Executable;

/**
 * Which program a document format needs on this server, and whether it is
 * missing, unless the document reader in use reads the format itself. A
 * file whose program is missing could be uploaded but never trained, so the
 * upload refuses it and the picker leaves it out. No format needs one now:
 * the app reads every format itself ({@see OfficeText}, {@see EbookText}),
 * and pandoc, catdoc and catppt are used only when they are installed.
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
        $extension = Str::lower($extension);

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
