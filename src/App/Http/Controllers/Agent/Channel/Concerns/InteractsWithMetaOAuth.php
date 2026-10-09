<?php

namespace App\Http\Controllers\Agent\Channel\Concerns;

use App\Http\Controllers\Agent\Channel\AgentInstagramChannelController;
use App\Http\Controllers\Agent\Channel\AgentMessengerChannelController;
use Domain\Agent\Models\Agent;
use Domain\Channel\Actions\CompleteMetaOAuthAction;
use Domain\Channel\Enums\MetaOAuthChannel;
use Domain\Channel\Services\MetaOAuthClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The HTTP half of connecting Messenger or Instagram through Facebook Login
 * for Business ({@see AgentMessengerChannelController},
 * {@see AgentInstagramChannelController}):
 *
 *   start    → stash the pending connection in the SESSION, bounce to consent
 *   complete → verify the returned state, list what was granted, hold it for the picker
 *
 * State and the granted accounts (with their page tokens) live in the session,
 * not the cache: the session cookie is what already survives the cross-site
 * redirect to Facebook and back. Session keys are `{channel}_oauth` (pending
 * state) and `{channel}_oauth_pages` (granted accounts). Page tokens never
 * reach the browser.
 */
trait InteractsWithMetaOAuth
{
    use ExplainsChannelConnectFailures;

    private function startMetaOAuth(Agent $agent, MetaOAuthChannel $channel, MetaOAuthClient $oauth): RedirectResponse
    {
        // Checked again by the connect action; refusing here spares the
        // workspace a trip to Facebook that can't succeed.
        if (! $agent->is_public) {
            return back()->withErrors([$channel->value => self::publicAgentsOnly($channel->label())]);
        }

        $state = Str::random(48);

        session()->put($channel->value.'_oauth', ['agent_id' => $agent->id, 'state' => $state]);

        Log::info("{$channel->label()} OAuth: starting connect", [
            'agent_id' => $agent->id,
            'has_app_id' => (bool) config('services.meta.app_id'),
            'has_app_secret' => (bool) config('services.meta.app_secret'),
        ]);

        return redirect()->away($oauth->authorizeUrl($state, $channel->scopes(), $channel->configId(), $channel->redirectUri()));
    }

    private function completeMetaOAuth(Request $request, MetaOAuthChannel $channel, CompleteMetaOAuthAction $complete): RedirectResponse
    {
        $pending = session()->pull($channel->value.'_oauth');
        $returnedState = (string) $request->query('state');

        if (! is_array($pending) || ! hash_equals((string) ($pending['state'] ?? ''), $returnedState)) {
            Log::warning("{$channel->label()} OAuth callback: state missing or mismatched", [
                'had_pending' => is_array($pending),
                'returned_state_present' => $returnedState !== '',
            ]);

            return redirect()->route('agents.index')->withErrors([
                $channel->value => __("That Facebook connection didn't match your session. Please try connecting again."),
            ]);
        }

        $agent = Agent::find($pending['agent_id']);

        if ($agent === null) {
            return redirect()->route('agents.index');
        }

        $code = (string) $request->query('code');

        if ($request->query('error') !== null || $code === '') {
            Log::warning("{$channel->label()} OAuth callback: Facebook returned no code", [
                'agent_id' => $agent->id,
                'error' => $request->query('error'),
                'error_reason' => $request->query('error_reason'),
            ]);

            return $this->backToChannels($agent, $channel, __('Facebook sign-in was cancelled. Please try again.'));
        }

        $accounts = $complete->execute($channel, $code);

        if ($accounts === []) {
            return $this->backToChannels($agent, $channel, self::nothingGranted($channel));
        }

        session()->put($channel->value.'_oauth_pages', [
            'agent_id' => $agent->id,
            'pages' => array_map(static fn ($account): array => $account->toArray(), $accounts),
        ]);

        Log::info("{$channel->label()} OAuth callback: accounts ready to pick", ['agent_id' => $agent->id, 'count' => count($accounts)]);

        return $this->backToChannels($agent, $channel);
    }

    /**
     * The granted accounts held in the session for this agent — empty when
     * there are none, or they belong to another agent.
     *
     * @return list<array<string, string>>
     */
    private static function pendingAccounts(Agent $agent, MetaOAuthChannel $channel): array
    {
        $stash = session($channel->value.'_oauth_pages');

        if (! is_array($stash) || ($stash['agent_id'] ?? null) !== $agent->id || ! is_array($stash['pages'] ?? null)) {
            return [];
        }

        return array_values($stash['pages']);
    }

    private static function forgetPendingAccounts(MetaOAuthChannel $channel): void
    {
        session()->forget($channel->value.'_oauth_pages');
    }

    private static function nothingGranted(MetaOAuthChannel $channel): string
    {
        return match ($channel) {
            MetaOAuthChannel::Messenger => __('No Facebook Pages came back. Re-run the connection and grant access to a Page with every permission checked.'),
            MetaOAuthChannel::Instagram => __('No Instagram professional accounts came back. Make sure your Instagram account is Professional and linked to a Facebook Page you manage, then reconnect.'),
        };
    }

    /**
     * Back to the Channels page with this channel's slide-over open — the OAuth
     * hop is a full-page redirect (not an Inertia XHR), so the sheet would
     * otherwise land closed and hide the picker or the error.
     */
    private function backToChannels(Agent $agent, MetaOAuthChannel $channel, ?string $error = null): RedirectResponse
    {
        $redirect = redirect()->route('agents.channels', ['agent' => $agent, 'open' => $channel->value]);

        return $error === null ? $redirect : $redirect->withErrors([$channel->value => $error]);
    }
}
