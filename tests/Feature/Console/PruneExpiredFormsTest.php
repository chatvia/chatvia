<?php

use Domain\Form\Models\FormSubmission;

it('deletes pending submissions past their TTL', function (): void {
    $expired = FormSubmission::factory()->create([
        'status' => FormSubmission::STATUS_PENDING,
        'expires_at' => now()->subHour(),
    ]);

    $this->artisan('app:prune-expired-forms')->assertSuccessful();

    expect(FormSubmission::find($expired->id))->toBeNull();
});

it('keeps pending submissions still within their TTL', function (): void {
    $live = FormSubmission::factory()->create([
        'status' => FormSubmission::STATUS_PENDING,
        'expires_at' => now()->addHour(),
    ]);

    $this->artisan('app:prune-expired-forms')->assertSuccessful();

    expect(FormSubmission::find($live->id))->not->toBeNull();
});

it('never deletes submitted captures, even when expires_at is in the past', function (): void {
    $submitted = FormSubmission::factory()->submitted()->create([
        'expires_at' => now()->subDay(),
    ]);

    $this->artisan('app:prune-expired-forms')->assertSuccessful();

    expect(FormSubmission::find($submitted->id))->not->toBeNull();
});
