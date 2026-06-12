<?php

namespace App\Http\Middleware\Auth;

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\PasswordSetup;
use Closure;
use Domain\Billing\Listeners\HandleStripeWebhook;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trial checkout provisions accounts with a random password the owner never
 * sees ({@see HandleStripeWebhook}). While the
 * PasswordSetup flag is present, every web request is steered to the
 * set-password page so the account gets a real password before anything else
 * happens. The setup routes themselves and logout stay reachable.
 */
class RequirePasswordSetup
{
    public function __construct(
        private readonly PasswordSetup $passwordSetup,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User
            || $request->routeIs('password.setup', 'password.setup.store', 'logout')
            || ! $this->passwordSetup->isRequired($user)) {
            return $next($request);
        }

        return redirect()->route('password.setup');
    }
}
