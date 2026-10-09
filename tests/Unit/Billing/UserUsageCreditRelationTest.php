<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Models\UserCredit;
use Domain\Billing\Models\UserUsage;

/**
 * A usage row belongs to its credit period through `user_credit_id`. The
 * relation is named `credit`, so it must name the column itself — Eloquent
 * would otherwise look for a `credit_id` column and always resolve null.
 */
it('resolves the credit period of a usage row, and back', function (): void {
    $credit = UserCredit::factory()->create(['user_id' => User::factory()->create()->id]);
    $usage = UserUsage::factory()->create(['user_credit_id' => $credit->id, 'messages' => 7]);

    expect($usage->fresh()->credit?->id)->toBe($credit->id)
        ->and($credit->fresh()->usage?->id)->toBe($usage->id);
});
