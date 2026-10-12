<?php

use Domain\Knowledge\Source\File\Events\FileIngestionProgressed;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * Locks the completion contract the frontend (Files.vue) depends on: when a file
 * is ingested, the broadcast must carry progress=100 on the agent's private
 * files-progress channel. The chunk jobs send the batch's progress, which never
 * reaches 100 by itself, so the batch's completion step re-emits this event as
 * the authoritative "finished" signal.
 */
it('broadcasts 100% on the agent files-progress channel when the file is ingested', function (): void {
    $file = new SourceFile;
    $file->id = 123;
    $file->status = 'ingested';

    $event = new FileIngestionProgressed($file, 'agent-ext-id');

    expect($event)->toBeInstanceOf(ShouldBroadcastNow::class)
        ->and($event->broadcastAs())->toBe('FileIngestionProgressed')
        ->and($event->broadcastWith())->toBe(['file_id' => 123, 'progress' => 100]);

    $channels = $event->broadcastOn();

    expect($channels[0])->toBeInstanceOf(PrivateChannel::class)
        ->and($channels[0]->name)->toBe('private-agents.agent-ext-id.files.progress');
});

it('sends the batch progress of a file that is training, capped below 100', function (int $batchProgress, int $sent): void {
    $file = new SourceFile;
    $file->id = 123;
    $file->status = 'ingesting';

    $event = new FileIngestionProgressed($file, 'agent-ext-id', $batchProgress);

    expect($event->broadcastWith()['progress'])->toBe($sent);
})->with([
    'part way' => [40, 40],
    'last job' => [100, 99],
]);
