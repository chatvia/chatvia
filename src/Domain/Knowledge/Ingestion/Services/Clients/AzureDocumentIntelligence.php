<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Services\Clients;

use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Exceptions\DocumentParsingException;
use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

/**
 * Azure AI Document Intelligence (Settings → AI providers): reads a PDF,
 * office file or image with its layout model, page by page, as Markdown
 * with its tables. The file is sent in the request, so the service never
 * needs to download it. Analysis runs in the background on Azure: the
 * client polls until it is done.
 *
 * Key, endpoint and model: `services.azure_document_intelligence.*`.
 */
class AzureDocumentIntelligence
{
    /** How long a document may take to analyze. */
    private const int MAX_WAIT_SECONDS = 900;

    /**
     * Analyze a file and return its pages.
     *
     * @return Collection<int, PageData>
     */
    public function analyze(string $path): Collection
    {
        $model = (string) config('services.azure_document_intelligence.model', 'prebuilt-layout');

        $started = $this->client()
            ->timeout(120)
            ->post($this->url("documentModels/{$model}:analyze").'&outputContentFormat=markdown', [
                'base64Source' => base64_encode(File::get($path)),
            ]);

        // Azure says why it refused a file (too large for the free tier, a
        // password lock, a format it cannot read); that reason is shown.
        if ($started->failed()) {
            throw DocumentParsingException::failed(mb_substr((string) (
                $started->json('error.innererror.message') ?? $started->json('error.message') ?? "Azure answered with an error ({$started->status()})."
            ), 0, 300));
        }

        $result = $this->wait($started->header('Operation-Location'));

        return $this->pages((array) ($result['analyzeResult'] ?? []));
    }

    /**
     * A call that needs the key and the endpoint, to check them: it succeeds
     * when both work, and throws a ConnectionException when the service
     * cannot be reached. A key or endpoint given is used instead of the
     * saved one.
     */
    public function check(?string $key = null, ?string $endpoint = null): Response
    {
        return $this->client($key)->timeout(20)->get($this->url('info', $endpoint));
    }

    /**
     * Poll the analysis until it is done.
     *
     * @return array<string, mixed>
     */
    private function wait(string $operation): array
    {
        if ($operation === '') {
            throw DocumentParsingException::failed('Azure Document Intelligence returned no operation to wait for.');
        }

        $deadline = now()->addSeconds(self::MAX_WAIT_SECONDS);

        while (true) {
            $response = $this->client()->timeout(60)->get($operation)->throw();
            $status = (string) $response->json('status');

            if ($status === 'succeeded') {
                return (array) $response->json();
            }

            if ($status === 'failed' || now()->gte($deadline)) {
                throw DocumentParsingException::failed(mb_substr((string) ($response->json('error.message') ?? 'Azure Document Intelligence did not finish.'), 0, 200));
            }

            Sleep::for(max(1, (int) ($response->header('Retry-After') ?: 2)))->seconds();
        }
    }

    /**
     * The analysis cut into pages by their spans in the Markdown, each page
     * with its tables as Markdown. A table's HTML is taken out of the
     * page's text, so its cells are not read twice.
     *
     * @param  array<string, mixed>  $result
     * @return Collection<int, PageData>
     */
    private function pages(array $result): Collection
    {
        $content = (string) ($result['content'] ?? '');
        $tables = $this->tablesByPage((array) ($result['tables'] ?? []));

        return collect((array) ($result['pages'] ?? []))
            ->values()
            ->map(function (array $page, int $index) use ($content, $tables): PageData {
                $number = (int) ($page['pageNumber'] ?? $index + 1);
                $markdown = collect((array) ($page['spans'] ?? []))
                    ->map(fn (array $span): string => mb_substr($content, (int) $span['offset'], (int) $span['length']))
                    ->join("\n");

                // Tables come separately; comments mark page breaks and numbers.
                $markdown = (string) preg_replace(['#<table\b.*?</table>#is', '#<!--.*?-->#s'], '', $markdown);

                $text = str(Markdown::convert($markdown)->getContent())
                    ->stripTags()
                    ->replaceMatches("/\n{3,}/", "\n\n")
                    ->trim()
                    ->toString();

                return new PageData(
                    pageNumber: $number,
                    content: $text,
                    checksum: hash('xxh128', $text),
                    tables: $tables[$number] ?? [],
                );
            });
    }

    /**
     * Each table as Markdown, by the page it starts on.
     *
     * @param  array<int, mixed>  $tables
     * @return array<int, list<string>>
     */
    private function tablesByPage(array $tables): array
    {
        $byPage = [];

        foreach ($tables as $table) {
            if (! is_array($table)) {
                continue;
            }

            $page = (int) ($table['boundingRegions'][0]['pageNumber'] ?? 1);
            $rows = array_fill(0, max(1, (int) ($table['rowCount'] ?? 1)), array_fill(0, max(1, (int) ($table['columnCount'] ?? 1)), ''));

            foreach ((array) ($table['cells'] ?? []) as $cell) {
                $rows[(int) ($cell['rowIndex'] ?? 0)][(int) ($cell['columnIndex'] ?? 0)] = str_replace(['|', "\n"], ['\\|', ' '], trim((string) ($cell['content'] ?? '')));
            }

            $lines = array_map(fn (array $row): string => '| '.implode(' | ', $row).' |', $rows);
            array_splice($lines, 1, 0, ['|'.str_repeat(' --- |', count($rows[0]))]);

            $byPage[$page][] = implode("\n", $lines);
        }

        return $byPage;
    }

    private function client(?string $key = null): PendingRequest
    {
        return Http::withHeaders(['Ocp-Apim-Subscription-Key' => (string) ($key ?? config('services.azure_document_intelligence.key'))])
            ->acceptJson();
    }

    /** An API address of the resource, with the API version. */
    private function url(string $path, ?string $endpoint = null): string
    {
        $base = rtrim((string) ($endpoint ?? config('services.azure_document_intelligence.endpoint')), '/');

        return "{$base}/documentintelligence/{$path}?api-version=".config('services.azure_document_intelligence.api_version', '2024-11-30');
    }
}
