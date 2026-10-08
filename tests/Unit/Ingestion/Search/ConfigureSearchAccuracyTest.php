<?php

use Domain\Knowledge\Ingestion\Models\Chunk;
use Illuminate\Support\Facades\DB;

/**
 * configureSearchAccuracy() interpolates the vchordrq settings into `SET
 * LOCAL` (SET takes no bind parameters), so the values must be cast to
 * numbers first — a config/env string can never reach the SQL as text.
 */
it('casts probes and epsilon to numbers before interpolating them', function (): void {
    $statements = [];
    DB::listen(function ($query) use (&$statements): void {
        $statements[] = $query->sql;
    });

    DB::transaction(function (): void {
        Chunk::query()->configureSearchAccuracy('default', [
            'search' => [
                'probes' => ['default' => '48; DROP TABLE chunks'],
                'epsilon' => ['default' => '1.5 ; SELECT 1'],
            ],
        ]);

        expect(DB::selectOne('SHOW vchordrq.probes')->{'vchordrq.probes'})->toBe('48')
            ->and((float) DB::selectOne('SHOW vchordrq.epsilon')->{'vchordrq.epsilon'})->toBe(1.5);
    });

    expect($statements)->toContain('SET LOCAL vchordrq.probes = 48')
        ->and($statements)->toContain('SET LOCAL vchordrq.epsilon = 1.5');
});

it('falls back to the default mode and never sets probes below one', function (): void {
    $statements = [];
    DB::listen(function ($query) use (&$statements): void {
        $statements[] = $query->sql;
    });

    DB::transaction(function (): void {
        Chunk::query()->configureSearchAccuracy('unknown_mode', [
            'search' => [
                'probes' => ['default' => 0],
                'epsilon' => ['default' => 1.9],
            ],
        ]);
    });

    expect($statements)->toContain('SET LOCAL vchordrq.probes = 1')
        ->and($statements)->toContain('SET LOCAL vchordrq.epsilon = 1.9');
});
