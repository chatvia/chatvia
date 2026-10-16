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

it('keeps the defaults of the 4 GB reference server', function (): void {
    $workers = new WorkerSizing()->recommend(new ServerResources(wsGib(4), null, 2));

    expect($workers->bySupervisor())->toBe([
        'chat' => 6, 'ingestion' => 4, 'analytics' => 2, 'media' => 1, 'voice' => 3,
    ]);
});

it('never goes below the defaults on a tiny server', function (): void {
    $workers = new WorkerSizing()->recommend(new ServerResources(wsGib(1), null, 1));

    expect($workers->bySupervisor())->toBe([
        'chat' => 6, 'ingestion' => 4, 'analytics' => 2, 'media' => 1, 'voice' => 3,
    ]);
});

it('scales up with the memory of the machine', function (): void {
    $workers = new WorkerSizing()->recommend(new ServerResources(wsGib(16), null, 8));

    expect($workers->bySupervisor())->toBe([
        'chat' => 24, 'ingestion' => 16, 'analytics' => 8, 'media' => 2, 'voice' => 12,
    ]);
});

it('caps the processor-bound pools by processor count', function (): void {
    $workers = new WorkerSizing()->recommend(new ServerResources(wsGib(16), null, 2));

    // Ingestion stays at two per processor; media at one per processor.
    expect($workers->ingestion)->toBe(4)
        ->and($workers->media)->toBe(2)
        ->and($workers->chat)->toBe(24);
});

it('stops scaling at eight times the reference server', function (): void {
    $workers = new WorkerSizing()->recommend(new ServerResources(wsGib(128), null, 32));

    expect($workers->bySupervisor())->toBe([
        'chat' => 48, 'ingestion' => 16, 'analytics' => 16, 'media' => 4, 'voice' => 24,
    ]);
});

it('sizes to the memory limit of a container instead of the host', function (): void {
    // 2 GiB for this container alone: 1.6 GiB of workers, not 32 GB / 4.
    $workers = new WorkerSizing()->recommend(new ServerResources(wsGib(32), wsGib(2), 8));

    expect($workers->chat)->toBe(10)
        ->and($workers->ingestion)->toBe(6)
        ->and($workers->voice)->toBe(5);
});
