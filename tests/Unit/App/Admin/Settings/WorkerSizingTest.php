<?php

use App\Admin\Settings\ServerResources;
use App\Admin\Settings\WorkerSizing;

/**
 * WorkerSizing: the worker numbers recommended for a server. The
 * config/horizon.php numbers fit a 4 GB server and are the floor; more
 * memory raises them, and the processor count caps ingestion and media.
 */
function wsGib(float $gib): int
{
    return (int) ($gib * 1024 ** 3);
}

it('keeps the defaults as the floor, scales with memory, caps the processor-bound pools and stops at eight times the reference server', function (int $gib, int $cpus, array $expected): void {
    $workers = new WorkerSizing()->recommend(new ServerResources(wsGib($gib), null, $cpus));

    expect($workers->bySupervisor())->toBe($expected);
})->with([
    'the 4 GB reference server' => [4, 2, ['chat' => 6, 'ingestion' => 4, 'analytics' => 2, 'records' => 2, 'media' => 1, 'voice' => 3, 'generation' => 2]],
    'a tiny server' => [1, 1, ['chat' => 6, 'ingestion' => 4, 'analytics' => 2, 'records' => 2, 'media' => 1, 'voice' => 3, 'generation' => 2]],
    '16 GB, 8 processors' => [16, 8, ['chat' => 24, 'ingestion' => 16, 'analytics' => 8, 'records' => 8, 'media' => 2, 'voice' => 12, 'generation' => 8]],
    '16 GB, 2 processors' => [16, 2, ['chat' => 24, 'ingestion' => 4, 'analytics' => 8, 'records' => 8, 'media' => 2, 'voice' => 12, 'generation' => 8]],
    '128 GB' => [128, 32, ['chat' => 48, 'ingestion' => 16, 'analytics' => 16, 'records' => 16, 'media' => 4, 'voice' => 24, 'generation' => 16]],
]);

it('sizes to the memory limit of a container instead of the host', function (): void {
    // 2 GiB for this container alone: 1.6 GiB of workers, not 32 GB / 4.
    $workers = new WorkerSizing()->recommend(new ServerResources(wsGib(32), wsGib(2), 8));

    expect($workers->chat)->toBe(10)
        ->and($workers->ingestion)->toBe(6)
        ->and($workers->voice)->toBe(5)
        ->and($workers->generation)->toBe(3);
});
