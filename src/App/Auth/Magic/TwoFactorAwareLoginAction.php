<?php

namespace App\Auth\Magic;

use App\Auth\Login\TwoFactorAwareLogin;
use App\Auth\Models\User;
use Illuminate\Http\RedirectResponse;
use MagicLink\Actions\ActionAbstract;

/**
 * Magic-link landing action. The package calls run() when the recipient opens
 * the emailed URL. Delegates the "log in vs. bounce to 2FA challenge" decision
 * to App\Auth\Login\TwoFactorAwareLogin so social and magic-link paths share
 * one rule.
 */
class TwoFactorAwareLoginAction extends ActionAbstract
{
    public function __construct(
        public int $userId,
        public string $redirectUrl,
        public bool $remember = false,
    ) {}

    public function run(): RedirectResponse
    {
        /** @var User|null $user */
        $user = User::query()->find($this->userId);

        if ($user === null) {
            return new RedirectResponse(route('login'));
        }

        return TwoFactorAwareLogin::redirect($user, $this->redirectUrl, $this->remember);
    }
}
