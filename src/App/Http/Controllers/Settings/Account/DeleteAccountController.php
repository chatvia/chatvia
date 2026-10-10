<?php

namespace App\Http\Controllers\Settings\Account;

use App\Identity\Workspace\Actions\Account\DeleteAccountAction;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Self-service deletion of a workspace account: the page and the final click.
 * Before that click the owner confirms their password and gets a signed email
 * link ({@see RequestAccountDeletionController}); opening the link arms the
 * deletion for this session for 30 minutes
 * ({@see ConfirmAccountDeletionController}). The link itself never deletes,
 * so email link scanners can't trigger it.
 */
class DeleteAccountController
{
    /**
     * Session key holding the unix timestamp at which the email confirmation
     * link was opened. `destroy()` requires this to be present and fresh.
     */
    public const string CONFIRMED_AT_KEY = 'account_deletion_confirmed_at';

    /** How long (seconds) a confirmed deletion stays armed before it lapses. */
    private const CONFIRMATION_TTL = 30 * 60;

    public function edit(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->isWorkspace(), 403);

        return Inertia::render('account/DeleteAccount', [
            'agents' => $user->agents()
                ->orderBy('name')
                ->get(['name'])
                ->map(fn (Agent $agent): array => ['name' => $agent->name])
                ->all(),
            'deletionConfirmed' => $this->confirmationIsFresh($request),
        ]);
    }

    /**
     * Step 2b — the final, explicit click. Requires a fresh confirmation marker
     * set by {@see ConfirmAccountDeletionController}; without it we bounce the
     * user back to re-request.
     */
    public function destroy(Request $request, DeleteAccountAction $deleteAccount): HttpResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->isWorkspace(), 403);

        if (! $this->confirmationIsFresh($request)) {
            return back()->withErrors([
                'confirmation' => __('Your confirmation link has expired. Please request account deletion again.'),
            ]);
        }

        Auth::logout();

        $deleteAccount->execute($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // The account and its session are gone. The home page ('/') is a
        // non-Inertia Blade view, so an Inertia visit can't follow a redirect into
        // it — return 204 and let the client do a full-page native redirect to '/'
        // (see DeleteUser.vue's onSuccess).
        return response()->noContent();
    }

    private function confirmationIsFresh(Request $request): bool
    {
        $confirmedAt = $request->session()->get(self::CONFIRMED_AT_KEY);

        return is_int($confirmedAt) && (now()->timestamp - $confirmedAt) <= self::CONFIRMATION_TTL;
    }
}
