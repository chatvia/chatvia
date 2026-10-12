<?php

declare(strict_types=1);

namespace App\Identity\Chatter\Actions;

use App\Identity\Chatter\Data\ResetChatterPasswordWithTokenData;
use App\Identity\Chatter\Services\ChatterPasswordResetTokens;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Support\Exceptions\DomainValidationException;

/**
 * Sets a chatter's new password from an emailed reset link. The token must be
 * the chatter's current, unexpired one; it is used up in the same transaction
 * as the password change, and the remember-me token is rotated so other
 * signed-in browsers lose their "remember me".
 */
class ResetChatterPasswordWithTokenAction
{
    public function __construct(
        private readonly ChatterPasswordResetTokens $tokens,
    ) {}

    /**
     * @throws DomainValidationException when the token is wrong or expired
     */
    public function execute(ResetChatterPasswordWithTokenData $data): void
    {
        if (! $this->tokens->isValid($data->chatter, $data->token)) {
            throw new DomainValidationException('token', trans('passwords.token'));
        }

        DB::transaction(function () use ($data): void {
            $data->chatter->forceFill([
                'password' => Hash::make($data->password),
                'remember_token' => Str::random(60),
            ])->save();

            $this->tokens->delete($data->chatter);
        });
    }
}
