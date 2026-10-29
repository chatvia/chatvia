<?php

use Domain\Knowledge\Ingestion\Services\ChunkImageLink;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;

/**
 * The link to an image of a chunk carries a signature of the chunk and the
 * image index under the app key, so only a link the server made is served:
 * no signature, a made-up one, or one minted for another chunk or image is
 * refused.
 */
beforeEach(function (): void {
    config()->set('app.key', 'base64:'.base64_encode(Str::repeat('k', 32)));
});

/** The signature in the link the server makes for this chunk image. */
function cilSignature(string $chunk, int $index): string
{
    $query = Uri::of(ChunkImageLink::path($chunk, $index))->query()->all();

    return $query[ChunkImageLink::SIGNATURE_PARAM];
}

it('verifies a signature produced by path()', function (): void {
    $externalId = '0192f4a1-7b3c-7abc-9def-0123456789ab';
    $query = Uri::of(ChunkImageLink::path($externalId, 2))->query()->all();

    expect(ChunkImageLink::verify($externalId, 2, $query[ChunkImageLink::SIGNATURE_PARAM]))->toBeTrue();
});

it('rejects a signature that is not the one minted for that chunk and image', function (string $chunk, int $index, ?string $signature): void {
    expect(ChunkImageLink::verify($chunk, $index, $signature))->toBeFalse();
})->with([
    'no signature' => ['abc', 0, null],
    'an empty signature' => ['abc', 0, ''],
    'the signature of another chunk' => fn (): array => ['chunk-b', 0, cilSignature('chunk-a', 0)],
    'the signature of another image' => fn (): array => ['chunk-a', 1, cilSignature('chunk-a', 0)],
    'a tampered signature' => ['chunk-a', 0, Str::repeat('0', 64)],
]);
