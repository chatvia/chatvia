<?php

namespace App\Http\Controllers\Billing\Trials;

use App\Http\Auth\TwoFactorAwareSignIn;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\PasswordSetup;
use Domain\Billing\Actions\Trials\ResolveTrialCheckoutAccountAction;
use Domain\Billing\Exceptions\InvalidTrialCheckoutException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Where Stripe sends a visitor after the trial checkout. A brand-new account
 * created by that checkout is signed in (through the two-factor check) and
 * asked to choose its password; an existing account is sent to the login page
 * instead; while the webhook has not created the account yet, a "processing"
 * page is shown. The rules live in {@see ResolveTrialCheckoutAccountAction}.
 */
class TrialSuccessController
{
    public function __invoke(
        Request $request,
        ResolveTrialCheckoutAccountAction $resolveAccount,
        TwoFactorAwareSignIn $signIn,
        PasswordSetup $passwordSetup,
    ): RedirectResponse|View {
        $sessionId = $request->query('session_id');

        if (! is_string($sessionId) || $sessionId === '') {
            return to_route('home')->with('error', __('Invalid session.'));
        }

        try {
            $result = $resolveAccount->execute($sessionId);
        } catch (InvalidTrialCheckoutException) {
            return to_route('home')->with('error', __('Unable to retrieve checkout session.'));
        } catch (Throwable $e) {
            report($e);

            return to_route('home')->with('error', __('Unable to complete login. Please check your email for login instructions.'));
        }

        $user = $result->user;

        // Stripe's webhook has not created the account yet: the customer gets
        // an e-mail once it has, and signs in from the login page.
        if (! $user instanceof User) {
            return to_route('login')->with('status', __('Your payment is being processed. We will e-mail you when your account is ready; then sign in here.'));
        }

        $next = $passwordSetup->isRequired($user) ? route('password.setup') : route('billing.success');

        if ($user->is($request->user())) {
            return redirect()->to($next);
        }

        if (! $result->maySignInAutomatically) {
            return to_route('login')->with('status', __('Your subscription is ready. Sign in to continue.'));
        }

        return $signIn->respond($user, $next);
    }
}
