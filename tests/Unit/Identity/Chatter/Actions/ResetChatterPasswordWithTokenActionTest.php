<?php

use App\Identity\Chatter\Actions\ResetChatterPasswordWithTokenAction;
use App\Identity\Chatter\Data\ResetChatterPasswordWithTokenData;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Chatter\Services\ChatterPasswordResetTokens;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Support\Exceptions\DomainValidationException;

/**
 * A chatter's emailed reset link sets a new password once. Tokens are kept per
 * chatter (not per email), so the same email in two workspaces never shares
 * one, and a used or expired token stops working.
 */
beforeEach(function (): void {
    $this->chatter = Chatter::factory()->create(['password' => Hash::make('old-password')]);
    $this->tokens = app(ChatterPasswordResetTokens::class);
});

function resetChatterPassword(Chatter $chatter, string $token, string $password = 'new-password-123'): void
{
    app(ResetChatterPasswordWithTokenAction::class)->execute(
        new ResetChatterPasswordWithTokenData(chatter: $chatter, token: $token, password: $password),
    );
}

it('sets the new password and uses up the token', function (): void {
    $token = $this->tokens->create($this->chatter);

    resetChatterPassword($this->chatter, $token);

    expect(Hash::check('new-password-123', $this->chatter->fresh()->password))->toBeTrue()
        ->and(DB::table('chatter_password_reset_tokens')->where('chatter_id', $this->chatter->id)->exists())->toBeFalse()
        ->and(fn () => resetChatterPassword($this->chatter, $token, 'again-123456'))->toThrow(DomainValidationException::class);
});

it('rotates the remember-me token, so other browsers are no longer remembered', function (): void {
    $this->chatter->forceFill(['remember_token' => 'remembered-browser-token'])->save();
    $token = $this->tokens->create($this->chatter);

    resetChatterPassword($this->chatter, $token);

    expect($this->chatter->fresh()->remember_token)->toBeString()
        ->not->toBe('remembered-browser-token')
        ->toHaveLength(60);
});

it('refuses a wrong token', function (): void {
    $this->tokens->create($this->chatter);

    $this->chatter->forceFill(['remember_token' => 'remembered-browser-token'])->save();

    expect(fn () => resetChatterPassword($this->chatter, 'not-the-token'))->toThrow(DomainValidationException::class)
        ->and(Hash::check('old-password', $this->chatter->fresh()->password))->toBeTrue()
        ->and($this->chatter->fresh()->remember_token)->toBe('remembered-browser-token');
});

it('refuses an expired token', function (): void {
    $token = $this->tokens->create($this->chatter);
    DB::table('chatter_password_reset_tokens')
        ->where('chatter_id', $this->chatter->id)
        ->update(['created_at' => now()->subMinutes(ChatterPasswordResetTokens::EXPIRE_MINUTES + 1)]);

    expect(fn () => resetChatterPassword($this->chatter, $token))->toThrow(DomainValidationException::class);
});

it('never accepts one chatter token for another chatter with the same email', function (): void {
    $twin = Chatter::factory()->create(['email' => $this->chatter->email, 'password' => Hash::make('twin-password')]);
    $token = $this->tokens->create($this->chatter);

    expect(fn () => resetChatterPassword($twin, $token))->toThrow(DomainValidationException::class)
        ->and(Hash::check('twin-password', $twin->fresh()->password))->toBeTrue();
});
