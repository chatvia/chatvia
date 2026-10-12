<?php

declare(strict_types=1);

namespace App\Http\Auth;

/**
 * The public forms the bot check (Cloudflare Turnstile) can protect. Each is
 * switched on or off in Settings → Security (`chatvia.security.turnstile.*`).
 */
enum BotCheckForm: string
{
    /** Creating a workspace account. */
    case SignUp = 'sign_up';

    /** Signing in with a password or asking for a sign-in link. */
    case SignIn = 'sign_in';

    /** Asking for a password reset link. */
    case PasswordReset = 'password_reset';
}
