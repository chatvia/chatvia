<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Domain\Knowledge\Ingestion\Services\DocumentReaders;
use Domain\Llm\Contracts\Transcriber;

/**
 * A program on the server that the app runs to read files or make PDFs. Its
 * path is a setting (Settings → Platform → Programs), so an install whose
 * programs are not on the PATH of the web server or the queue worker (such as
 * Homebrew on a Mac) can still use them.
 *
 * Only the program's own name, or a full path that ends in it, is accepted
 * ({@see acceptsPath()}). Any other path would let a stolen admin login make
 * the server run a command of its choice.
 */
enum ServerProgram: string
{
    case Pdfinfo = 'pdfinfo';
    case Pdftotext = 'pdftotext';
    case Pandoc = 'pandoc';
    case Catdoc = 'catdoc';
    case Catppt = 'catppt';
    case Ffmpeg = 'ffmpeg';
    case Ffprobe = 'ffprobe';
    case Weasyprint = 'weasyprint';

    /** The {@see PlatformSettings} property that holds its path. */
    public function property(): string
    {
        return $this->value.'_path';
    }

    /**
     * What the app runs it for, shown under its field. It also says what
     * happens without it: the app does the work itself, a document reader
     * (Mistral OCR or Azure) or another PDF maker does it, or some files
     * cannot be uploaded. So a "Not found" can be judged.
     */
    public function purpose(): string
    {
        return match ($this) {
            self::Pdfinfo => 'Counts the pages of PDF files and finds locked ones. Without it, the app does this itself. Part of poppler.',
            self::Pdftotext => 'Only a backup: the document reader (Mistral OCR or Azure) reads PDF files. This program reads a PDF only if it is trained again after the reader is turned off. Part of poppler.',
            self::Pandoc => 'Reads RTF files, which no document reader reads. Without it, .rtf files cannot be uploaded. It also reads EPUB books and counts the pages of Word (.docx) files. Without it, the app reads EPUB books itself, and the page count is an estimate.',
            self::Catdoc => 'Reads old Word files (.doc). Mistral OCR and Azure do not read them, so without it, .doc files cannot be uploaded.',
            self::Catppt => 'Reads old PowerPoint files (.ppt). Mistral OCR and Azure do not read them, so without it, .ppt files cannot be uploaded. Part of catdoc.',
            self::Ffmpeg => 'Compresses audio and video for speech to text, when a file is too large for the service or in a format it does not take. Needed only when speech to text is on.',
            self::Ffprobe => 'Measures how long audio and video files are. Without it, the app does this itself. Part of ffmpeg.',
            self::Weasyprint => 'Makes PDFs (chat exports, and the PDF of an office file for its summary) when it is the PDF maker. Not needed with Cloudflare or Gotenberg.',
        };
    }

    /**
     * What stops working while the program is not found, with the settings
     * in use, and any other way to get it back; null when nothing does (the
     * app does the work itself, or the program is not used now).
     */
    public function lossWhenMissing(): ?string
    {
        return match ($this) {
            self::Pdfinfo, self::Ffprobe => null,
            self::Pdftotext => app(DocumentReaders::class)->available() ? null : __('PDF files cannot be trained again while no document reader is on. Install it, or turn on Mistral OCR or Azure.'),
            self::Pandoc => __('.rtf files cannot be uploaded.'),
            self::Catdoc => __('.doc files cannot be uploaded.'),
            self::Catppt => __('.ppt files cannot be uploaded.'),
            self::Ffmpeg => app(Transcriber::class)->isEnabled() ? __('Recordings over 25 MB, or in a format the speech-to-text service does not take, cannot be turned into text.') : null,
            self::Weasyprint => PdfMaker::inUse() === PdfMaker::Weasyprint ? __('Chat PDF exports and summaries of office files fail. Install it, or pick Cloudflare or Gotenberg as the PDF maker.') : null,
        };
    }

    /**
     * Whether it reads uploaded files, so that without it some file types
     * cannot be read. The others make PDFs or prepare recordings for speech
     * to text.
     */
    public function readsUploads(): bool
    {
        return match ($this) {
            self::Pdfinfo, self::Pdftotext, self::Pandoc, self::Catdoc, self::Catppt => true,
            self::Ffmpeg, self::Ffprobe, self::Weasyprint => false,
        };
    }

    /**
     * Whether the path may be saved for this program: its own name, looked
     * up on the PATH, or a full path whose last part is its name.
     */
    public function acceptsPath(string $path): bool
    {
        return $path === $this->value
            || (str_starts_with($path, '/') && str_ends_with($path, '/'.$this->value) && preg_match('/[\x00-\x1F\x7F]/', $path) !== 1);
    }
}
