<?php

namespace Domain\Knowledge\Ingestion\Services\Clients;

use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Source\File\Services\SourceFileTypes;
use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Enums\LlmProviderEnum;
use Domain\Llm\Services\ProviderAdapters;
use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Client for Mistral's hosted OCR API. Turns PDFs, office files and images into
 * one PageData per page, with the images and tables found on each page. Used
 * through MistralDocumentParser; the model is `services.mistral.ocr_model`.
 */
class MistralOcr
{
    private readonly LlmProviderInterface $mistral;

    private readonly string $baseUrl;

    public function __construct(ProviderAdapters $adapters)
    {
        // The Mistral account (Settings → AI providers), not a catalog model.
        $adapter = $adapters->for(LlmProviderEnum::MISTRAL);
        $connection = $adapter->standalone();

        $this->mistral = $adapter->client($connection);
        $this->baseUrl = $adapter->baseUrl($connection);
    }

    /**
     * OCR a PDF or office file that Mistral can fetch from the URL.
     *
     * @return Collection<int,PageData>
     */
    public function fromUrl(string $url): Collection
    {
        return $this->read(['type' => 'document_url', 'document_url' => $url]);
    }

    /**
     * OCR an image that Mistral can fetch from the URL (images go under
     * `image_url`, not `document_url`).
     *
     * @return Collection<int,PageData>
     */
    public function fromImageUrl(string $url): Collection
    {
        return $this->read(['type' => 'image_url', 'image_url' => $url]);
    }

    /**
     * Reads a small built-in image, to check the key, the OCR model and the
     * account at once: a model list can work for a key that OCR refuses. It
     * succeeds when all three work, and throws a ConnectionException when
     * Mistral cannot be reached. The image is sent in the request, so this
     * site need not be reachable from the internet.
     */
    public function check(): Response
    {
        return $this->mistral
            ->client()
            ->timeout(30)
            ->post($this->baseUrl.'/ocr', [
                'model' => (string) config('services.mistral.ocr_model'),
                'document' => [
                    'type' => 'image_url',
                    'image_url' => 'data:image/png;base64,'.base64_encode((string) file_get_contents(resource_path('checks/document-reader.png'))),
                ],
            ]);
    }

    /**
     * @param  array<string, string>  $document  the OCR request's `document` part
     * @return Collection<int,PageData>
     */
    private function read(array $document): Collection
    {
        $response = $this->mistral
            ->client()
            ->timeout(3600)
            ->post($this->baseUrl.'/ocr', [
                'model' => (string) config('services.mistral.ocr_model'),
                'document' => $document,
                'table_format' => 'markdown',
                'include_image_base64' => true,
            ])->throw();

        /** @var list<array{index: int, markdown: string, images: list<array{image_base64: string}>|null, tables: list<array{content: string}>|null, hyperlinks?: list<mixed>}> $pages */
        $pages = $response->json('pages');

        return collect($pages)->map(function (array $page): PageData {

            $images = [];
            $tables = [];

            if ($page['images']) {
                $images = collect($page['images'])->map(fn ($image): mixed => $image['image_base64'])->toArray();
            }

            if ($page['tables']) {
                $tables = collect($page['tables'])->map(fn (array $table): string => $table['content'])->toArray();
            }

            $text = str(Markdown::convert($page['markdown'])->getContent())
                ->stripTags()
                ->trim()
                ->toString();

            return new PageData(
                // Mistral counts pages from 0; page numbers start at 1.
                pageNumber: (int) $page['index'] + 1,
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
     * The copy is written to the given folder (the source's `tmp/` folder),
     * so deleting the source or its workspace wipes a copy a crash left
     * behind, and it is removed as soon as the OCR call returns or fails.
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

            return SourceFileTypes::isImage($file) ? $this->fromImageUrl($tempUrl) : $this->fromUrl($tempUrl);
        } finally {
            Storage::delete($filePath);
        }
    }
}
