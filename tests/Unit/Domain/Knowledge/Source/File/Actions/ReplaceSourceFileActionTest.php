<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Jobs\TextFile\TextFileIngestionJob;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\File\Actions\ReplaceSourceFileAction;
use Domain\Knowledge\Source\File\Actions\UploadKnowledgeSourceFileAction;
use Domain\Knowledge\Source\File\Data\UploadedSourceFileData;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * Replacing a knowledge file keeps the file (its id and agents) but swaps its
 * content: the old stored object and every old chunk go, and the new version
 * is trained again.
 */
beforeEach(function (): void {
    Storage::fake();
    Queue::fake();
});

function rsfUpload(string $name, string $content): UploadedSourceFileData
{
    return new UploadedSourceFileData(
        path: tempTestFile($name, $content),
        originalName: $name,
        mimeType: 'text/plain',
        clientMimeType: 'text/plain',
        sizeInBytes: strlen($content),
    );
}

it('swaps the content, removes the old version and its chunks, and trains again', function (): void {
    $agent = Agent::factory()->create();
    $file = app(UploadKnowledgeSourceFileAction::class)->execute($agent, rsfUpload('prices-2025.txt', 'Starter costs 39.'));
    $oldPath = $file->filepath;
    Chunk::factory()->forSource($file)->create(['chunk' => 'Starter costs 39.']);

    $replaced = app(ReplaceSourceFileAction::class)->execute($agent, $file, rsfUpload('prices-2026.txt', 'Starter costs 49.'));

    expect($replaced->id)->toBe($file->id)
        ->and($replaced->filename)->toBe('prices-2026.txt')
        ->and($replaced->checksum)->toBe(md5('Starter costs 49.'))
        ->and(Chunk::query()->where('chunkable_id', $file->id)->where('chunkable_type', $file->getMorphClass())->exists())->toBeFalse();

    Storage::assertMissing($oldPath);
    Storage::assertExists($replaced->filepath);
    Queue::assertPushed(TextFileIngestionJob::class);
});
