<?php

use App\Admin\Settings\StorageCheck;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * StorageCheck: a disk passes when a test file is written, read back and
 * deleted; with a public URL the file must also load from that address,
 * so a bucket that is not really public fails before logos break.
 */
it('passes a disk that writes, reads and deletes the test file', function (): void {
    $disk = Storage::fake('scratch');

    expect(app(StorageCheck::class)->checkDisk($disk, 'The bucket files'))->toBeNull()
        ->and($disk->allFiles())->toBe([]);
});

it('passes a public bucket whose files load from its public URL', function (): void {
    $disk = Storage::fake('scratch');
    Http::fake(fn ($request) => Http::response($disk->get(Str::afterLast($request->url(), '/'))));

    expect(app(StorageCheck::class)->checkDisk($disk, 'The bucket images', 'https://images.example.org'))->toBeNull()
        ->and($disk->allFiles())->toBe([]);

    Http::assertSent(fn ($request): bool => Str::startsWith($request->url(), 'https://images.example.org/chatvia-storage-test-'));
});

it('fails a public bucket whose files do not load from its public URL, and still deletes the test file', function (): void {
    $disk = Storage::fake('scratch');
    Http::fake(['images.example.org/*' => Http::response('AccessDenied', 403)]);

    expect(app(StorageCheck::class)->checkDisk($disk, 'The bucket images', 'https://images.example.org'))
        ->toBe('The bucket images works, but its files do not load from https://images.example.org. Turn on public read access for the bucket, and check the public URL.')
        ->and($disk->allFiles())->toBe([]);
});
