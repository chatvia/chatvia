<?php

use App\Identity\Chatter\Actions\PruneExpiredChatterPasswordResetTokensAction;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Chatter\Services\ChatterPasswordResetTokens;
use Illuminate\Support\Facades\DB;

/**
 * Chatter reset tokens past their hour are deleted; a token that still works
 * stays and still works.
 */
it('deletes expired chatter reset tokens and keeps the live one', function (): void {
    $tokens = app(ChatterPasswordResetTokens::class);
    $expired = Chatter::factory()->create();
    $live = Chatter::factory()->create();

    $tokens->create($expired);
    DB::table('chatter_password_reset_tokens')->where('chatter_id', $expired->id)->update(['created_at' => now()->subMinutes(61)]);
    $liveToken = $tokens->create($live);

    $deleted = app(PruneExpiredChatterPasswordResetTokensAction::class)->execute();

    expect($deleted)->toBe(1)
        ->and(DB::table('chatter_password_reset_tokens')->where('chatter_id', $expired->id)->exists())->toBeFalse()
        ->and($tokens->isValid($live, $liveToken))->toBeTrue();
});
