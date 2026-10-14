<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\File\Enums;

use Domain\Knowledge\Ingestion\Services\DocumentReaders;
use Domain\Llm\Contracts\Transcriber;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A group of file formats that workspaces may upload as knowledge. An admin
 * turns each group on or off (Settings → Knowledge → File types, config
 * `chatvia.knowledge.file_types`). Documents are recognised by their
 * extension; audio and video by their content, so their extensions only fill
 * the file picker. The values are stored in the setting and sent to the
 * dashboard, so they must not change.
 */
#[TypeScript]
enum KnowledgeFileType: string
{
    case Pdf = 'pdf';
    case Word = 'word';
    case Spreadsheet = 'spreadsheet';
    case Presentation = 'presentation';
    case Text = 'text';
    case Ebook = 'ebook';
    case Image = 'image';
    case Audio = 'audio';
    case Video = 'video';

    /**
     * The types this install accepts, in declaration order. Unknown values in
     * the setting are ignored. Types no service can read are left out: they
     * could be uploaded but never trained. Audio and video need speech to
     * text; PDF, Word, slides and images a document reader (Mistral OCR or
     * Azure Document Intelligence).
     *
     * @return list<self>
     */
    public static function enabled(): array
    {
        $values = (array) config('chatvia.knowledge.file_types', []);

        return array_values(array_filter(
            self::cases(),
            fn (self $type): bool => in_array($type->value, $values, true) && $type->isReadable(),
        ));
    }

    /**
     * Whether a service can read files of the type now: speech to text for
     * audio and video, a document reader for PDF, Word, slides and images.
     * The rest the app reads itself.
     */
    public function isReadable(): bool
    {
        return match (true) {
            $this->isMedia() => app(Transcriber::class)->isEnabled(),
            $this->needsDocumentReader() => app(DocumentReaders::class)->available(),
            default => true,
        };
    }

    /** PDF, Word, slides and images: only a document reader reads them well. */
    public function needsDocumentReader(): bool
    {
        return in_array($this, [self::Pdf, self::Word, self::Presentation, self::Image], true);
    }

    public function isEnabled(): bool
    {
        return in_array($this, self::enabled(), true);
    }

    /** Audio and video: recognised by their content and measured in minutes. */
    public function isMedia(): bool
    {
        return $this === self::Audio || $this === self::Video;
    }

    /**
     * The file extensions of the type, lower case and without the dot. For
     * documents they match config/mimes.php `ocr`, which picks how each one
     * is read.
     *
     * @return list<string>
     */
    public function extensions(): array
    {
        return match ($this) {
            self::Pdf => ['pdf'],
            self::Word => ['doc', 'docx', 'rtf'],
            self::Spreadsheet => ['xlsx', 'csv'],
            self::Presentation => ['ppt', 'pptx'],
            self::Text => ['txt', 'md', 'markdown'],
            self::Ebook => ['epub', 'fb2'],
            self::Image => ['jpg', 'jpeg', 'png', 'avif', 'tiff', 'tif', 'gif', 'heic', 'heif', 'bmp', 'webp'],
            self::Audio => ['mp3', 'm4a', 'wav', 'ogg', 'webm', 'flac', 'aac', 'wma', 'amr', '3gp'],
            self::Video => ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv', 'qt', '3gp'],
        };
    }
}
