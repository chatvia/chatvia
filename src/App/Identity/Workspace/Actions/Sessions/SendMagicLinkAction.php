<?php

namespace App\Identity\Workspace\Actions\Sessions;

use App\Http\Auth\MagicLinkSignIn;
use App\Identity\Workspace\Data\Sessions\SendMagicLinkData;
use App\Identity\Workspace\Events\MagicLinkRequested;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use MagicLink\MagicLink;

/**
 * Issue a single-use magic sign-in link and email it to the address.
 *
 * Silently no-ops when no matching user exists, when the account is
 * deactivated, or (when scoped to an agent) when the user can't access that
 * agent, so the caller can return the same "if the account exists, you'll get
 * a link" response either way — we don't want this endpoint to leak whether
 * an email is registered. Nothing is logged on these refusals: a log line with
 * the email and the agent would record who probed what.
 */
class SendMagicLinkAction
{
    private const LIFETIME_MINUTES = 15;

    public function execute(SendMagicLinkData $data, string $redirectUrl, ?Agent $agent = null): void
    {
        $email = strtolower(trim($data->email));

        $user = User::query()->where('email', $email)->first();

        if ($user === null || $user->isDeactivated()) {
            return;
        }

        if ($agent !== null && ! $user->canAccessAgent($agent)) {
            return;
        }

        $magicLink = MagicLink::create(
            action: new MagicLinkSignIn(
                userId: $user->id,
                redirectUrl: $redirectUrl,
            ),
            lifetime: self::LIFETIME_MINUTES,
            numMaxVisits: 1,
        );

        MagicLinkRequested::dispatch($email, $magicLink->url, $agent?->name);
    }
}
