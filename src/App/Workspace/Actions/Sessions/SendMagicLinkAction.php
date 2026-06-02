<?php

namespace App\Workspace\Actions\Sessions;

use App\Workspace\Data\Sessions\SendMagicLinkData;
use App\Workspace\Events\MagicLinkRequested;
use App\Workspace\Magic\TwoFactorAwareLoginAction;
use App\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Illuminate\Support\Facades\Log;
use MagicLink\MagicLink;

/**
 * Issue a single-use magic sign-in link and email it to the address.
 *
 * Silently no-ops when no matching user exists (or, when scoped to an agent,
 * when the user can't access that agent) so the caller can return the same
 * "if the account exists, you'll get a link" response either way — we don't
 * want this endpoint to leak whether an email is registered.
 */
class SendMagicLinkAction
{
    private const LIFETIME_MINUTES = 15;

    public function execute(SendMagicLinkData $data, string $redirectUrl, ?Agent $agent = null): void
    {
        $email = strtolower(trim($data->email));

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            Log::info('Magic link skipped: no user matches email.', ['email' => $email]);

            return;
        }

        if ($agent !== null && ! $user->canAccessAgent($agent)) {
            Log::info('Magic link skipped: user cannot access agent.', [
                'email' => $email,
                'user_id' => $user->id,
                'agent_id' => $agent->id,
            ]);

            return;
        }

        $magicLink = MagicLink::create(
            action: new TwoFactorAwareLoginAction(
                userId: $user->id,
                redirectUrl: $redirectUrl,
            ),
            lifetime: self::LIFETIME_MINUTES,
            numMaxVisits: 1,
        );

        MagicLinkRequested::dispatch($email, $magicLink->url, $agent?->name);
    }
}
