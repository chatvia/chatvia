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

    /** What the app runs it for, shown under its field. */
    public function purpose(): string
    {
        return match ($this) {
            self::Pdfinfo => 'Counts the pages of PDF files. Part of poppler.',
            self::Pdftotext => 'Reads the text of PDF files. Part of poppler.',
            self::Pandoc => 'Reads Word (.docx), ODT, RTF, EPUB and FB2 files.',
            self::Catdoc => 'Reads old Word files (.doc).',
            self::Catppt => 'Reads old PowerPoint files (.ppt). Part of catdoc.',
            self::Ffmpeg => 'Prepares audio and video for speech to text.',
            self::Ffprobe => 'Measures how long audio and video files are. Part of ffmpeg.',
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
