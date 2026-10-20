<?php

namespace Domain\Knowledge\Ingestion\Services\Enhancement;

use Domain\Knowledge\Ingestion\Data\Chunking\PageVisualsData;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Llm\Operations\Documents\Tables;
use Domain\Llm\Operations\Media\Vision;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Describes the images and tables the document parser found on a page. Each
 * image is described by the vision model and, when it carries information,
 * saved as a JPEG in the given storage folder so the chat can show it later.
 * With `chatvia.ai.chunker.describe_images` off (no model that reads images
 * is set up), images are skipped. All tables of the page are described
 * together in one call.
 */
class PageVisualsDescriber
{
    public function __construct(
        private readonly Vision $vision,
        private readonly Tables $tables,
    ) {}

    public function describe(PageData $page, string $imageDirectory): PageVisualsData
    {
        $tables = array_values(array_filter(
            array_map(trim(...), $page->tables),
            static fn (string $table): bool => $table !== '',
        ));

        return new PageVisualsData(
            imageDescriptions: filter_var(config('chatvia.ai.chunker.describe_images'), FILTER_VALIDATE_BOOL)
                ? $this->describeImages($page->imagesBase64, $imageDirectory)
                : [],
            tables: $tables,
            tableDescription: $page->tables !== [] ? $this->tables->describe($page->tables) : '',
        );
    }

    /**
     * @param  list<string>  $imagesBase64
     * @return array<string, string> storage path => description
     */
    private function describeImages(array $imagesBase64, string $imageDirectory): array
    {
        $described = [];

        foreach ($imagesBase64 as $imageBase64) {
            $jpeg = Image::decode($imageBase64)->encode(new JpegEncoder)->toString();
            $description = $this->vision->describeImageBytes($jpeg);

            // Ids only in the log: the image and its description are customer content.
            logger()->debug('Page image described', ['described' => $description !== null]);

            if ($description === null) {
                continue;
            }

            $path = rtrim($imageDirectory, '/').'/'.Str::uuid()->toString().'.jpg';
            Storage::put($path, $jpeg);

            $described[$path] = $description;
        }

        return $described;
    }
}
