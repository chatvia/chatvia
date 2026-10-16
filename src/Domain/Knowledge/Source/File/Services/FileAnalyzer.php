<?php

namespace Domain\Knowledge\Source\File\Services;

use Domain\Knowledge\Document\Conversion\OfficeText;
use Domain\Knowledge\Document\Conversion\Pandoc;
use Domain\Knowledge\Document\Conversion\PdfInfo;
use Domain\Knowledge\Ingestion\Services\Media\AudioDurationExtractor;
use Domain\Knowledge\Source\File\Data\FileAnalysisData;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

/**
 * Computes a file's "length" at upload time — pages for documents/images,
 * minutes for audio/video. It is shown in the sources UI and stored on
 * `source_files.length`. The document parser returns the true page count only
 * during ingestion, so this is a pre-flight estimate:
 *
 *   - PDF            → pdfinfo (exact)
 *   - Office/text    → OfficeText pages (slides and sheets exact; other
 *                      formats estimated, one page per ~3000 characters)
 *   - Images         → 1 page
 *   - Ebooks (Pandoc) → extract text, ~500 words per page (estimate)
 *   - Audio/Video    → duration in minutes
 */
class FileAnalyzer
{
    /** Average words per page used to estimate page count from extracted text. */
    private const int WORDS_PER_PAGE = 500;

    /** Rough bytes per page when a document cannot be read at all. */
    private const int BYTES_PER_PAGE = 3000;

    public function __construct(
        private readonly AudioDurationExtractor $audioDuration,
        private readonly PdfInfo $pdfInfo,
        private readonly Pandoc $pandoc,
        private readonly OfficeText $officeText,
    ) {}

    /**
     * @param  string  $path  local path of the file
     * @param  string|null  $extension  the file's real extension, when the path has none
     *                                  (PHP keeps uploads in extensionless temp files)
     *
     * @throws LogicException when the type is neither a supported document nor media
     */
    public function analyze(string $path, ?string $extension = null): FileAnalysisData
    {
        $extension = strtolower($extension ?? pathinfo($path, PATHINFO_EXTENSION));

        return match (true) {
            is_audio_file($path), is_video_file($path) => new FileAnalysisData(length: $this->audioDuration->getDurationInMinutes($path)),
            $extension === 'pdf', is_pdf_file($path) => $this->pdfInfo->getInfo($path),
            in_array($extension, config('mimes.ocr.image'), true) => new FileAnalysisData(length: 1),
            in_array($extension, config('mimes.ocr.office'), true) => $this->analyzeOfficeFile($path, $extension),
            in_array($extension, config('mimes.ocr.pandoc'), true) => new FileAnalysisData(length: $this->estimateEbookPages($path, $extension)),
            default => throw new LogicException('Invalid file type!'),
        };
    }

    /**
     * Count the pages OfficeText reads from the file.
     */
    private function analyzeOfficeFile(string $path, string $extension): FileAnalysisData
    {
        try {
            return new FileAnalysisData(length: count($this->officeText->pages($path, $extension)));
        } catch (Throwable $e) {
            // Only a pre-flight estimate — ingestion reports the true page
            // count later. A file can fail to read (corrupt, password-
            // protected), which must not fail the upload, so fall back to a
            // rough size-based estimate.
            Log::warning('FileAnalyzer: reading the office file failed, using size estimate', [
                'extension' => $extension,
                'exception' => $e->getMessage(),
            ]);

            return new FileAnalysisData(length: $this->estimatePagesFromSize($path));
        }
    }

    private function estimateEbookPages(string $path, string $extension): int
    {
        try {
            $words = str_word_count($this->pandoc->toText($path, $extension));

            return max(1, (int) ceil($words / self::WORDS_PER_PAGE));
        } catch (Throwable) {
            // Best-effort: Pandoc can't always parse a corrupt or malformed
            // ebook. Fall back to a rough size-based estimate so the upload
            // still proceeds; ingestion reports the true count later.
            return $this->estimatePagesFromSize($path);
        }
    }

    private function estimatePagesFromSize(string $path): int
    {
        return max(1, (int) ceil((File::size($path) ?: 0) / self::BYTES_PER_PAGE));
    }
}
