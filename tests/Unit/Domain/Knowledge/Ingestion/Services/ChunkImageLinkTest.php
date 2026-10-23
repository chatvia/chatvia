<?php

use Domain\Knowledge\Ingestion\Services\ChunkImageLink;

beforeEach(function (): void {
    config()->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
});

it('builds a path carrying a signature for the chunk + index', function (): void {
    $path = ChunkImageLink::path('0192f4a1-7b3c-7abc-9def-0123456789ab', 0);

    expect($path)->toStartWith('/my/chunks/0192f4a1-7b3c-7abc-9def-0123456789ab/0.png?')
        ->and($path)->toContain(ChunkImageLink::SIGNATURE_PARAM.'=');
});

it('verifies a signature produced by path()', function (): void {
    $externalId = '0192f4a1-7b3c-7abc-9def-0123456789ab';
    parse_str(parse_url(ChunkImageLink::path($externalId, 2), PHP_URL_QUERY), $query);

    expect(ChunkImageLink::verify($externalId, 2, $query[ChunkImageLink::SIGNATURE_PARAM]))->toBeTrue();
});

it('rejects a missing or empty signature', function (): void {
    expect(ChunkImageLink::verify('abc', 0, null))->toBeFalse()
        ->and(ChunkImageLink::verify('abc', 0, ''))->toBeFalse();
});

it('rejects a signature minted for a different chunk id', function (): void {
    parse_str(parse_url(ChunkImageLink::path('chunk-a', 0), PHP_URL_QUERY), $query);

    expect(ChunkImageLink::verify('chunk-b', 0, $query[ChunkImageLink::SIGNATURE_PARAM]))->toBeFalse();
});

it('rejects a signature minted for a different image index', function (): void {
    parse_str(parse_url(ChunkImageLink::path('chunk-a', 0), PHP_URL_QUERY), $query);

    expect(ChunkImageLink::verify('chunk-a', 1, $query[ChunkImageLink::SIGNATURE_PARAM]))->toBeFalse();
});

it('rejects a tampered signature', function (): void {
    expect(ChunkImageLink::verify('chunk-a', 0, str_repeat('0', 64)))->toBeFalse();
});

it('produces a different signature under a different app key', function (): void {
    parse_str(parse_url(ChunkImageLink::path('chunk-a', 0), PHP_URL_QUERY), $first);

    config()->set('app.key', 'base64:'.base64_encode(str_repeat('z', 32)));
    parse_str(parse_url(ChunkImageLink::path('chunk-a', 0), PHP_URL_QUERY), $second);

    expect($first[ChunkImageLink::SIGNATURE_PARAM])->not->toBe($second[ChunkImageLink::SIGNATURE_PARAM]);
});
