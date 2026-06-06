<?php

use Domain\Knowledge\Ingestion\Services\Clients\LlamaParse;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Wire-format coverage for the LlamaParse client (review's #3 named gap). Covers
 * parse() / getJobStatus() / getImages() / error paths. getPages() polls with a
 * literal sleep(30) inside an infinite loop and is intentionally NOT tested
 * here — that polling loop is the seam to refactor before it's testable; a test
 * would either run for 30s+ or require injecting a clock the client doesn't
 * accept.
 */
beforeEach(function (): void {
    config()->set('services.llamaparse.endpoint', 'https://parse.test/api');
    config()->set('services.llamaparse.api_key', 'llama-test-key');
});

function makeTempPdf(string $contents = 'fake-pdf-bytes'): File
{
    $path = tempnam(sys_get_temp_dir(), 'llama').'.pdf';
    file_put_contents($path, $contents);

    return new File($path);
}

it('parse() uploads the file + options envelope and returns the job id', function (): void {
    Http::fake([
        'parse.test/api/upload' => Http::response(['id' => 'job-abc-123'], 200),
    ]);

    $jobId = app(LlamaParse::class)->parse(makeTempPdf());

    expect($jobId)->toBe('job-abc-123');

    Http::assertSent(function (Request $request): bool {
        // The body is multipart form-data; assert on the raw body string
        // (offsetGet doesn't reliably round-trip multipart fields in this
        // Laravel version, but the wire content does carry them).
        $body = $request->body();

        return $request->method() === 'POST'
            && $request->url() === 'https://parse.test/api/upload'
            && $request->hasHeader('Authorization', 'Bearer llama-test-key')
            && is_string($body)
            && str_contains($body, 'parse_page_with_llm')
            && str_contains($body, 'name="parse_mode"');
    });
});

it('parse() throws when the upload response is missing the job id', function (): void {
    Http::fake([
        'parse.test/api/upload' => Http::response(['unexpected' => 'shape'], 200),
    ]);

    expect(fn () => app(LlamaParse::class)->parse(makeTempPdf()))
        ->toThrow(RuntimeException::class, 'Job ID not found');
});

it('parse() propagates upstream HTTP errors via ->throw()', function (): void {
    Http::fake([
        'parse.test/api/upload' => Http::response(['error' => 'unauthorized'], 401),
    ]);

    expect(fn () => app(LlamaParse::class)->parse(makeTempPdf()))
        ->toThrow(RequestException::class);
});

it('getJobStatus() GETs the job url with bearer auth', function (): void {
    Http::fake([
        'parse.test/api/job/job-status-1' => Http::response(['status' => 'SUCCESS'], 200),
    ]);

    $response = app(LlamaParse::class)->getJobStatus('job-status-1');

    expect($response->json('status'))->toBe('SUCCESS');

    Http::assertSent(fn (Request $r): bool => $r->method() === 'GET'
        && $r->url() === 'https://parse.test/api/job/job-status-1'
        && $r->hasHeader('Authorization', 'Bearer llama-test-key'));
});

it('getImages() downloads each image to a temp dir with name/path/size shape', function (): void {
    Http::fake([
        'parse.test/api/job/job-9/result/image/img-a.png' => Http::response('AAAA', 200),
        'parse.test/api/job/job-9/result/image/img-b.png' => Http::response('BBBBBB', 200),
    ]);

    $images = app(LlamaParse::class)->getImages('job-9', ['img-a.png', 'img-b.png']);

    expect($images)->toHaveCount(2)
        ->and($images[0]['name'])->toBe('job-9_img-a.png')
        ->and($images[0]['size'])->toBe(4)
        ->and(file_get_contents($images[0]['path']))->toBe('AAAA')
        ->and($images[1]['name'])->toBe('job-9_img-b.png')
        ->and($images[1]['size'])->toBe(6)
        ->and(file_get_contents($images[1]['path']))->toBe('BBBBBB');
});

it('getImages() throws when an image fetch fails', function (): void {
    Http::fake([
        '*result/image/missing.png' => Http::response('not found', 404),
    ]);

    expect(fn () => app(LlamaParse::class)->getImages('job-x', ['missing.png']))
        ->toThrow(Exception::class, '404');
});
