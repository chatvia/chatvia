<?php

use Domain\Form\Actions\PruneAbandonedFormSubmissionsAction;
use Domain\Form\Enums\FormSubmissionStatus;
use Domain\Form\Models\FormSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Only pending forms past their time limit are deleted; live pending forms
 * and captured submissions stay.
 */
it('deletes only expired pending submissions and reports how many', function (): void {
    $expired = FormSubmission::factory()->create(['status' => FormSubmissionStatus::Pending, 'expires_at' => now()->subMinute()]);
    $live = FormSubmission::factory()->create(['status' => FormSubmissionStatus::Pending, 'expires_at' => now()->addHour()]);
    $captured = FormSubmission::factory()->submitted()->create(['expires_at' => now()->subDay()]);

    expect(app(PruneAbandonedFormSubmissionsAction::class)->execute())->toBe(1)
        ->and(FormSubmission::query()->whereKey($expired->id)->exists())->toBeFalse()
        ->and(FormSubmission::query()->whereKey($live->id)->exists())->toBeTrue()
        ->and(FormSubmission::query()->whereKey($captured->id)->exists())->toBeTrue();
});

it('deletes expired placeholders in batches until none is left', function (): void {
    $expired = FormSubmission::factory()->count(3)->create(['status' => FormSubmissionStatus::Pending, 'expires_at' => now()->subMinute()]);
    $live = FormSubmission::factory()->create(['status' => FormSubmissionStatus::Pending, 'expires_at' => now()->addHour()]);
    DB::enableQueryLog();

    expect(app(PruneAbandonedFormSubmissionsAction::class)->execute(batchSize: 1))->toBe(3)
        // Three batches of one, then one that finds nothing.
        ->and(collect(DB::getQueryLog())->filter(fn (array $query): bool => Str::startsWith($query['query'], 'delete'))->count())->toBe(4)
        ->and(FormSubmission::query()->whereKey($expired->pluck('id'))->exists())->toBeFalse()
        ->and(FormSubmission::query()->whereKey($live->id)->exists())->toBeTrue();
});
