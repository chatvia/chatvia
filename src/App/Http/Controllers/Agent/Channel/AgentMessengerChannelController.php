<?php

namespace App\Http\Controllers\Agent\Channel;

use Domain\Agent\Models\Agent;
use Domain\Channel\Messenger\Actions\ConnectMessengerPageAction;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Channel\Services\MetaOAuthClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Connects an agent's Facebook Page through Facebook Login for Business — one
 * Chatvia Meta app for every workspace, so customers click "Connect with
 * Facebook" instead of pasting tokens. The flow:
 *
 *   redirect()  → stash the pending connection in the SESSION, bounce to consent
 *   callback()  → verify the returned state, swap code → Page tokens, hold for the picker
 *   select()    → the workspace picks which Page powers this agent; store + subscribe it
 *
 * State + the granted Page tokens live in the session (not the cache): the
 * session cookie is what already survives the cross-site redirect to Facebook
 * and back, so this has no dependency on the cache store being shared across
 * php-fpm workers. Page tokens never reach the browser. Disconnect is a single
 * delete. All gated by `agent.setup`. Messaging channels are public-agents only.
 */
class AgentMessengerChannelController
{
    public function __construct(private readonly MetaOAuthClient $oauth) {}

    public function redirect(Agent $agent): RedirectResponse
    {
        if (! $agent->is_public) {
            return back()->withErrors([
                'messenger' => 'Messenger is available for public agents only. Make this agent public on the Security page, then connect it.',
            ]);
        }

        $state = Str::random(48);

        session()->put('messenger_oauth', ['agent_id' => $agent->id, 'state' => $state]);

        // Confirms (at connect time) whether the Meta config is actually loaded
        // — if config_id/redirect are null here, config:cache wasn't rebuilt.
        Log::info('Messenger OAuth: starting connect', [
            'agent_id' => $agent->id,
            'has_app_id' => (bool) config('services.meta.app_id'),
            'has_app_secret' => (bool) config('services.meta.app_secret'),
            'config_id' => config('services.meta.config_id'),
            'redirect_uri' => config('services.meta.redirect'),
        ]);

        return redirect()->away($this->oauth->authorizeUrl($state, MetaOAuthClient::MESSENGER_SCOPES));
    }

    public function callback(Request $request): RedirectResponse
    {
        Log::info('Messenger OAuth: callback hit', [
            'has_code' => (string) $request->query('code') !== '',
            'has_state' => (string) $request->query('state') !== '',
            'error' => $request->query('error'),
        ]);

        $pending = session()->pull('messenger_oauth');
        $returnedState = (string) $request->query('state');

        if (! is_array($pending) || ! hash_equals((string) ($pending['state'] ?? ''), $returnedState)) {
            Log::warning('Messenger OAuth callback: state missing or mismatched', [
                'had_pending' => is_array($pending),
                'returned_state_present' => $returnedState !== '',
            ]);

            return redirect()->route('agents.index')->withErrors([
                'messenger' => "That Facebook connection didn't match your session. Please try connecting again.",
            ]);
        }

        $agent = Agent::find($pending['agent_id']);

        if ($agent === null) {
            return redirect()->route('agents.index');
        }

        if ($request->query('error') !== null || (string) $request->query('code') === '') {
            Log::warning('Messenger OAuth callback: Facebook returned no code', [
                'agent_id' => $agent->id,
                'error' => $request->query('error'),
                'error_reason' => $request->query('error_reason'),
            ]);

            return $this->backToChannels($agent, 'Facebook sign-in was cancelled. Please try again.');
        }

        $userToken = $this->oauth->exchangeCodeForUserToken((string) $request->query('code'));
        $pages = $userToken !== null ? $this->oauth->pages($userToken) : [];

        if ($pages === []) {
            Log::warning('Messenger OAuth callback: no Pages returned', [
                'agent_id' => $agent->id,
                'got_user_token' => $userToken !== null,
            ]);

            return $this->backToChannels($agent, 'No Facebook Pages came back. Re-run the connection and grant access to a Page with every permission checked.');
        }

        // Hold the granted Pages (id + name + token) server-side in the session
        // until the workspace picks one. The picker only ever sees id + name.
        session()->put('messenger_oauth_pages', ['agent_id' => $agent->id, 'pages' => $pages]);

        Log::info('Messenger OAuth callback: Pages ready to pick', ['agent_id' => $agent->id, 'count' => count($pages)]);

        return $this->backToChannels($agent);
    }

    public function select(Request $request, Agent $agent, ConnectMessengerPageAction $action): RedirectResponse
    {
        $chosen = collect($this->pendingPages($agent))->firstWhere('id', (string) $request->input('page_id'));

        if (! is_array($chosen)) {
            return back()->withErrors(['messenger' => 'That Page selection expired. Please reconnect.']);
        }

        $takenByAnother = AgentMessengerPage::query()
            ->where('page_id', $chosen['id'])
            ->where('agent_id', '!=', $agent->id)
            ->exists();

        if ($takenByAnother) {
            return back()->withErrors(['messenger' => 'That Facebook Page is already connected to another agent. Disconnect it there first.']);
        }

        $action->execute($agent, (string) $chosen['id'], (string) $chosen['name'], (string) $chosen['access_token']);
        session()->forget('messenger_oauth_pages');

        return back();
    }

    public function disconnect(Agent $agent): RedirectResponse
    {
        $agent->messengerPage?->delete();
        session()->forget('messenger_oauth_pages');

        return back();
    }

    /**
     * The Pages held in the session for this agent (empty if none / for a
     * different agent).
     *
     * @return list<array{id: string, name: string, access_token: string}>
     */
    private function pendingPages(Agent $agent): array
    {
        $stash = session('messenger_oauth_pages');

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

        return $error === null ? $redirect : $redirect->withErrors(['messenger' => $error]);
    }
}
