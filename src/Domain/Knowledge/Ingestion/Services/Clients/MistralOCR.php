<?php

namespace Domain\Knowledge\Ingestion\Services\Clients;

use Domain\Knowledge\Ingestion\Data\OCR\PageData;
use Domain\Llm\Providers\Mistral;
use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Client for Mistral's hosted OCR API. Turns PDFs, office files and images into
 * one PageData per page, with the images and tables found on each page. Used
 * through MistralDocumentParser; the model is `services.mistral.ocr_model`.
 */
class MistralOCR
{
    public function __construct(
        public Mistral $mistral
    ) {}

    /**
     * @return Collection<int,PageData>
     */
    public function fromUrl(string $url, bool $isImage = false): Collection
    {
        // Mistral OCR takes images under image_url and everything else (PDF,
        // Office) under document_url.
        $document = $isImage
            ? ['type' => 'image_url', 'image_url' => $url]
            : ['type' => 'document_url', 'document_url' => $url];

        $response = $this->mistral
            ->client()
            ->timeout(3600)
            ->post(rtrim($this->mistral->getBaseUrl(), '/').'/ocr', [
                'model' => (string) config('services.mistral.ocr_model'),
                'document' => $document,
                'table_format' => 'markdown',
                'include_image_base64' => true,
            ])->throw();

        $pages = $response->json('pages');

        return collect($pages)->map(function (array $page) {

            $images = [];
            $tables = [];

            if ($page['images']) {
                $images = collect($page['images'])->map(function ($image) {
                    return $image['image_base64'];
                })->toArray();
            }

            if ($page['tables']) {
                $tables = collect($page['tables'])->map(function (array $table) {
                    return $table['content'];
                })->toArray();
            }

            $text = str(Markdown::convert($page['markdown'])->getContent())
                ->stripTags()
                ->trim()
                ->toString();

            $pageNumber = (int) $page['index'];

            if ($pageNumber === 0) {
                $pageNumber++;
            }

            return new PageData(
                pageNumber: $pageNumber,
                content: $text,
                checksum: hash('xxh128', $text),
                imagesBase64: $images,
                tables: $tables,
                hyperlinks: $page['hyperlinks'] ?? [],
            );
        });
    }

    /**
     * OCR a local file by uploading a short-lived copy that Mistral can fetch.
     *
     * The copy is written under the owner's storage prefix (for example
     * `users/{ws}/agents/{agent}/tmp`) so an account or agent deletion wipes
     * it too, and it is removed as soon as the OCR call returns or fails.
     *
     * @return Collection<int,PageData>
     */
    public function fromFile(string $file, string $storageDirectory): Collection
    {
        if (! file_exists($file)) {
            throw new InvalidArgumentException('File does not exist');
        }

        $extension = pathinfo($file, PATHINFO_EXTENSION);

        $filePath = rtrim($storageDirectory, '/').'/'.Str::uuid()->toString().'.'.$extension;

        Storage::put($filePath, file_get_contents($file));

        try {
            $tempUrl = Storage::temporaryUrl($filePath, now()->addHour());

            return $this->fromUrl($tempUrl, is_image_file($file));
        } finally {
            Storage::delete($filePath);
        }
    }
}
