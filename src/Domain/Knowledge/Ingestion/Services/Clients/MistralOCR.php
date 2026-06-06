<?php

namespace Domain\Knowledge\Ingestion\Services\Clients;

use Domain\Knowledge\Ingestion\Data\OCR\PageData;
use Domain\Llm\Providers\Mistral;
use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class MistralOCR
{
    public function __construct(
        public Mistral $mistral
    ) {}

    /**
     * @return Collection<int,PageData>
     */
    public function fromUrl(string $url): Collection
    {
        $response = $this->mistral
            ->client()
            ->timeout(3600)
            ->post('https://api.mistral.ai/v1/ocr', [
                'model' => 'mistral-ocr-latest',
                'document' => [
                    'type' => 'document_url',
                    'document_url' => $url,
                ],
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

    public function fromFile(string $file): Collection
    {
        if (! file_exists($file)) {
            throw new InvalidArgumentException('File does not exist');
        }

        $extension = pathinfo($file, PATHINFO_EXTENSION);

        $filePath = 'temp/'.Str::uuid()->toString().'.'.$extension;

        Storage::put($filePath, file_get_contents($file));

        $tempUrl = Storage::temporaryUrl($filePath, now()->addDay());

        try {
            $response = $this->fromUrl($tempUrl);
        } catch (Throwable $exception) {
            Storage::delete($filePath);
            throw $exception;
        }

        return $response;
    }
}
