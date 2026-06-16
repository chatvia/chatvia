<?php

namespace App\Http\Controllers\Agent\Channel;

use Domain\Agent\Models\Agent;
use Domain\Channel\Instagram\Actions\ConnectInstagramAccountAction;
use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Domain\Channel\Services\MetaOAuthClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Connects an agent's Instagram professional account through Facebook Login for
 * Business — one Chatvia Meta app for every workspace, so customers click
 * "Connect with Facebook" instead of pasting tokens. Identical shape to
 * {@see AgentMessengerChannelController}: redirect → callback → select. The IG
 * account is reached through its linked Facebook Page (the Page token sends the
 * DMs). State + the granted accounts live in the session, not the cache; the
 * page tokens never reach the browser. Instagram has its own Login-for-Business
 * configuration + callback URL. Messaging channels are public-agents only.
 */
class AgentInstagramChannelController
{
    public function __construct(private readonly MetaOAuthClient $oauth) {}

    public function redirect(Agent $agent): RedirectResponse
    {
        if (! $agent->is_public) {
            return back()->withErrors([
                'instagram' => 'Instagram is available for public agents only. Make this agent public on the Security page, then connect it.',
            ]);
        }

        $state = Str::random(48);
        session()->put('instagram_oauth', ['agent_id' => $agent->id, 'state' => $state]);

        $configId = config('services.meta.instagram_config_id') ?: config('services.meta.config_id');
        $redirect = config('services.meta.instagram_redirect') ?: config('services.meta.redirect');

        return redirect()->away($this->oauth->authorizeUrl(
            $state,
            MetaOAuthClient::INSTAGRAM_SCOPES,
            is_string($configId) && $configId !== '' ? $configId : null,
            is_string($redirect) && $redirect !== '' ? $redirect : null,
        ));
    }

    public function callback(Request $request): RedirectResponse
    {
        $pending = session()->pull('instagram_oauth');
        $returnedState = (string) $request->query('state');

        if (! is_array($pending) || ! hash_equals((string) ($pending['state'] ?? ''), $returnedState)) {
            Log::warning('Instagram OAuth callback: state missing or mismatched', [
                'had_pending' => is_array($pending),
                'returned_state_present' => $returnedState !== '',
            ]);

            return redirect()->route('agents.index')->withErrors([
                'instagram' => "That Facebook connection didn't match your session. Please try connecting again.",
            ]);
        }

        $agent = Agent::find($pending['agent_id']);

        if ($agent === null) {
            return redirect()->route('agents.index');
        }

        if ($request->query('error') !== null || (string) $request->query('code') === '') {
            Log::warning('Instagram OAuth callback: Facebook returned no code', [
                'agent_id' => $agent->id,
                'error' => $request->query('error'),
                'error_reason' => $request->query('error_reason'),
            ]);

            return $this->backToChannels($agent, 'Facebook sign-in was cancelled. Please try again.');
        }

        $redirect = config('services.meta.instagram_redirect') ?: config('services.meta.redirect');
        $userToken = $this->oauth->exchangeCodeForUserToken(
            (string) $request->query('code'),
            is_string($redirect) && $redirect !== '' ? $redirect : null,
        );
        $accounts = $userToken !== null ? $this->oauth->pagesWithInstagram($userToken) : [];

        if ($accounts === []) {
            Log::warning('Instagram OAuth callback: no IG-linked Pages returned', [
                'agent_id' => $agent->id,
                'got_user_token' => $userToken !== null,
            ]);

            return $this->backToChannels($agent, 'No Instagram professional accounts came back. Make sure your Instagram account is Professional and linked to a Facebook Page you manage, then reconnect.');
        }

        // Held server-side until the workspace picks one — the picker only ever
        // sees the IG username + Page name; the page token stays here.
        session()->put('instagram_oauth_pages', ['agent_id' => $agent->id, 'pages' => $accounts]);

        Log::info('Instagram OAuth callback: accounts ready to pick', ['agent_id' => $agent->id, 'count' => count($accounts)]);

        return $this->backToChannels($agent);
    }

    public function select(Request $request, Agent $agent, ConnectInstagramAccountAction $action): RedirectResponse
    {
        $chosen = collect($this->pendingPages($agent))->firstWhere('ig_id', (string) $request->input('ig_id'));

        if (! is_array($chosen)) {
            return back()->withErrors(['instagram' => 'That account selection expired. Please reconnect.']);
        }

        $takenByAnother = AgentInstagramAccount::query()
            ->where('ig_id', $chosen['ig_id'])
            ->where('agent_id', '!=', $agent->id)
            ->exists();

        if ($takenByAnother) {
            return back()->withErrors(['instagram' => 'That Instagram account is already connected to another agent. Disconnect it there first.']);
        }

        $action->execute(
            $agent,
            (string) $chosen['ig_id'],
            (string) $chosen['ig_username'],
            (string) $chosen['page_id'],
            (string) $chosen['page_access_token'],
        );
        session()->forget('instagram_oauth_pages');

        return back();
    }

    public function disconnect(Agent $agent): RedirectResponse
    {
        $agent->instagramAccount?->delete();
        session()->forget('instagram_oauth_pages');

        return back();
    }

    /**
     * @return list<array{page_id: string, page_name: string, page_access_token: string, ig_id: string, ig_username: string}>
     */
    private function pendingPages(Agent $agent): array
    {
        $stash = session('instagram_oauth_pages');

        if (! is_array($stash) || ($stash['agent_id'] ?? null) !== $agent->id || ! is_array($stash['pages'] ?? null)) {
            return [];
        }

        return $stash['pages'];
    }

    private function backToChannels(?Agent $agent, ?string $error = null): RedirectResponse
    {
        if ($agent === null) {
            return redirect()->route('agents.index');
        }

        $redirect = redirect()->route('agents.channels', $agent);

        return $error === null ? $redirect : $redirect->withErrors(['instagram' => $error]);
    }
}
