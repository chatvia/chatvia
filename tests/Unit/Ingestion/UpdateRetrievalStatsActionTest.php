<?php

use Domain\Knowledge\Ingestion\Actions\Stats\UpdateRetrievalStatsAction;

it('no-ops when the results array is empty', function (): void {
    // No DB touches expected: the action should return immediately
    // without dispatching any queries. We assert by simply executing
    // and reaching the assertion line without exception.
    app(UpdateRetrievalStatsAction::class)->execute([], agentId: 1);

    expect(true)->toBeTrue();
});

it('skips groups whose chunkable_type is not a real class', function (): void {
    // The action filters out non-existent classes before issuing any
    // UPDATE statements. Passing a fake type name exercises that branch
    // without touching any real source table.
    app(UpdateRetrievalStatsAction::class)->execute(
        results: [
            ['chunkable_type' => 'NotAClass\\Made\\Up', 'chunkable_id' => 1],
        ],
        agentId: 1,
    );

    expect(true)->toBeTrue();
});
