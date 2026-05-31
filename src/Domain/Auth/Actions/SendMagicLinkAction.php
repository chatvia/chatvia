<?php

namespace Domain\Auth\Actions;

use App\Auth\Magic\TwoFactorAwareLoginAction;
use App\Auth\Models\User;
use App\Auth\Notifications\MagicLinkNotification;
use Domain\Agents\Models\Agent;
use Domain\Auth\Data\SendMagicLinkData;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
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

        Notification::route('mail', $email)
            ->notify(new MagicLinkNotification(
                url: $magicLink->url,
                contextName: $agent?->name,
            ));
    }
}
