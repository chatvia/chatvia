<?php

declare(strict_types=1);

namespace App\Admin\Settings;

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
     * What the app runs it for, shown under its field. It also says when the
     * program is needed, and when a document reader (Mistral OCR or Azure)
     * does the work instead, so a "Not found" can be judged.
     */
    public function purpose(): string
    {
        return match ($this) {
            self::Pdfinfo => 'Counts the pages of PDF files and finds locked ones. Needed for PDF files, with any document reader. Part of poppler.',
            self::Pdftotext => 'Only a backup: the document reader (Mistral OCR or Azure) reads PDF files. This program reads a PDF only if it is trained again after the reader is turned off. Part of poppler.',
            self::Pandoc => 'Reads EPUB, FB2 and RTF files, which no document reader reads. Also counts the pages of Word (.docx) files.',
            self::Catdoc => 'Reads old Word files (.doc). Mistral OCR and Azure do not read them, so this program is needed for them.',
            self::Catppt => 'Reads old PowerPoint files (.ppt). Mistral OCR and Azure do not read them, so this program is needed for them. Part of catdoc.',
            self::Ffmpeg => 'Prepares audio and video files, and voice notes in the hosted chat, for speech to text. Needed only when speech to text is on.',
            self::Ffprobe => 'Measures how long audio and video files are. Needed only when speech to text is on. Part of ffmpeg.',
            self::Weasyprint => 'Makes PDFs: chat exports, and the PDF of an office file for its summary.',
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
