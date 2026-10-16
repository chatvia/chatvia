<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Source;

use Carbon\CarbonInterface;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Spatie\LaravelData\Data;

/**
 * A knowledge source of an agent: an uploaded file, a block of text or a
 * website. The list and the three create endpoints return this one shape;
 * fields that do not apply to a kind are null. Poll the list and watch
 * `status` to see when training is done.
 */
class SourceData extends Data
{
    public function __construct(
        /**
         * The source's ID. Use it to delete the source.
         */
        public string $id,
        /**
         * What the source is: `file`, `text` or `url`.
         */
        public string $kind,
        /**
         * The file name, the start of the text, or the website address.
         */
        public string $name,
        /**
         * The file's MIME type. Null for texts and websites.
         */
        public ?string $mime_type,
        /**
         * The file's size in kilobytes. Null for texts and websites.
         */
        public ?int $size_kb,
        /**
         * The text's length in pages. Null for files and websites.
         */
        public ?int $length_pages,
        /**
         * Where the source is in training.
         */
        public SourceStatus $status,
        /**
         * When the source was added.
         */
        public ?CarbonInterface $created_at,
    ) {}

    public static function fromModel(SourceFile|SourceText|SourceWebsite $source): self
    {
        return match (true) {
            $source instanceof SourceFile => new self(
                id: (string) $source->external_id,
                kind: 'file',
                name: $source->filename,
                mime_type: $source->mime_type,
                size_kb: (int) $source->size_in_kb,
                length_pages: null,
                status: $source->status,
                created_at: $source->created_at,
            ),
            $source instanceof SourceText => new self(
                id: (string) $source->external_id,
                kind: 'text',
                name: str($source->excerpt)->limit(60)->toString(),
                mime_type: null,
                size_kb: null,
                length_pages: $source->length,
                status: $source->status,
                created_at: $source->created_at,
            ),
            default => new self(
                id: (string) $source->external_id,
                kind: 'url',
                name: $source->url,
                mime_type: null,
                size_kb: null,
                length_pages: null,
                status: $source->status,
                created_at: $source->created_at,
            ),
        };
    }
}
