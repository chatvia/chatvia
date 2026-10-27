<?php

namespace Domain\Knowledge\Ingestion\Services\Clients;

use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Source\File\Services\SourceFileTypes;
use Domain\Llm\Contracts\ChatCompletionProvider;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Services\AiCallTags;
use Domain\Llm\Services\ProviderAdapters;
use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Symfony\Component\Mime\MimeTypes;

/**
 * Client for Mistral's hosted OCR API. Turns PDFs, office files and images into
 * one PageData per page, with the images and tables found on each page. Used
 * through MistralDocumentParser; the model is `services.mistral.ocr_model`.
 *
 * The file goes inside the request as a data: URL, never as a link to this
 * site: Mistral cannot open a link to a local or private install.
 */
class MistralOcr
{
    private readonly ChatCompletionProvider $mistral;

    private readonly string $baseUrl;

    public function __construct(
        ProviderAdapters $adapters,
        private readonly AiCallTags $tags,
    ) {
        // The Mistral account (Settings → AI providers), not a catalog model.
        $adapter = $adapters->for(LlmProviderKind::Mistral);
        $connection = $adapter->standalone();

        $this->mistral = $adapter->client($connection);
        $this->baseUrl = $adapter->baseUrl($connection);
    }

    /**
     * OCR a local PDF, office file or image. The data: URL is about a third
     * larger than the file; it is built inside the call, not kept here, so
     * read() can free it once the request is sent.
     *
     * @return Collection<int,PageData>
     */
    public function fromFile(string $file): Collection
    {
        if (! File::exists($file)) {
            throw new InvalidArgumentException('File does not exist');
        }

        // Images go under `image_url`, not `document_url`.
        return $this->read(SourceFileTypes::isImage($file)
            ? ['type' => 'image_url', 'image_url' => $this->dataUrl($file)]
            : ['type' => 'document_url', 'document_url' => $this->dataUrl($file)]);
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
        return $this->tags->during(AiCallTag::ServiceCheck, fn (): Response => $this->mistral
            ->client()
            ->timeout(30)
            ->post($this->baseUrl.'/ocr', [
                'model' => (string) config('services.mistral.ocr_model'),
                'document' => [
                    'type' => 'image_url',
                    'image_url' => 'data:image/png;base64,'.base64_encode(File::get(resource_path('checks/document-reader.png'))),
                ],
            ]));
    }

    /**
     * @param  array<string, string>  $document  the OCR request's `document` part
     * @return Collection<int,PageData>
     */
    private function read(array $document): Collection
    {
        $response = $this->tags->during(AiCallTag::DocumentReading, fn (): Response => $this->mistral
            ->client()
            ->timeout(3600)
            ->post($this->baseUrl.'/ocr', [
                'model' => (string) config('services.mistral.ocr_model'),
                'document' => $document,
                'table_format' => 'markdown',
                'include_image_base64' => true,
            ])->throw());

        // The file is sent: free its copy before the pages are decoded.
        unset($document);

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

    /** The file as a data: URL, typed by its extension (which also chose Mistral to read it). */
    private function dataUrl(string $file): string
    {
        $mimeType = MimeTypes::getDefault()->getMimeTypes(file_extension($file))[0] ?? 'application/octet-stream';

        return 'data:'.$mimeType.';base64,'.base64_encode(File::get($file));
    }
}
