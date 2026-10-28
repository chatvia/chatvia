<?php

namespace App\Identity\Workspace\Actions\Sessions;

use App\Http\Auth\MagicLinkSignIn;
use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Data\Sessions\SendMagicLinkData;
use App\Identity\Workspace\Events\MagicLinkRequested;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Illuminate\Support\Str;
use MagicLink\MagicLink;

/**
 * Issue a single-use magic sign-in link and email it to the address. A link
 * scoped to an agent signs in to that agent's hosted chat, not the dashboard:
 * first as a chatter of the agent's workspace with that email, otherwise as a
 * staff account that can reach the agent (whose link still runs two-factor).
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
    private const int LIFETIME_MINUTES = 15;

    public function execute(SendMagicLinkData $data, string $redirectUrl, ?Agent $agent = null): void
    {
        $email = Str::lower(Str::trim($data->email));

        // Staff emails are stored lowercase, so a plain match uses the unique index.
        $account = $agent instanceof Agent
            ? $this->chatterFor($agent, $email) ?? User::query()->where('email', $email)->first()
            : User::query()->where('email', $email)->first();

        if ($account === null || $account->isDeactivated()) {
            return;
        }

        if ($agent instanceof Agent && ! $account->canAccessAgent($agent)) {
            return;
        }

        $magicLink = MagicLink::create(
            action: new MagicLinkSignIn(
                userId: $account instanceof User ? $account->id : null,
                redirectUrl: $redirectUrl,
                agentId: $agent?->id,
                chatterId: $account instanceof Chatter ? $account->id : null,
            ),
            lifetime: self::LIFETIME_MINUTES,
            numMaxVisits: 1,
        );

        // On APP_URL even when asked from an agent's custom domain: the link
        // runs on the dashboard session, which only APP_URL serves.
        $url = $magicLink->baseUrl((string) config('app.url'))->url;

        MagicLinkRequested::dispatch($email, $url, $agent?->name);
    }

    /** A chatter of the agent's workspace with this email, if any. */
    private function chatterFor(Agent $agent, string $email): ?Chatter
    {
        return Chatter::query()
            ->where('workspace_id', $agent->workspace_id)
            ->whereRaw('lower(email) = ?', [$email])
            ->first();
    }
}
