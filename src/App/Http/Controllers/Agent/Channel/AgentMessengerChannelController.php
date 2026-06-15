<?php

namespace App\Http\Controllers\Agent\Channel;

use Domain\Agent\Models\Agent;
use Domain\Channel\Messenger\Actions\ConnectMessengerPageAction;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Channel\Services\MetaOAuthClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Connects an agent's Facebook Page through Facebook Login for Business — one
 * Chatvia Meta app for every workspace, so customers click "Connect with
 * Facebook" instead of creating their own app and pasting tokens. The flow:
 *
 *   redirect()  → stash who's connecting, bounce to Facebook's consent screen
 *   callback()  → swap the code for the user's Page tokens, hold them for the picker
 *   select()    → the workspace picks which Page powers this agent; we store + subscribe it
 *
 * Page tokens never reach the browser — they live in a short-lived server cache
 * between callback and select. Disconnect is a single delete. All gated by
 * `agent.setup`. Messaging channels are public-agents only.
 */
class AgentMessengerChannelController
{
    /** Time to complete the round-trip (consent → pick a Page). */
    private const STATE_TTL = 600;

    public function __construct(private readonly MetaOAuthClient $oauth) {}

    public function redirect(Agent $agent): RedirectResponse
    {
        if (! $agent->is_public) {
            return back()->withErrors([
                'messenger' => 'Messenger is available for public agents only. Make this agent public on the Security page, then connect it.',
            ]);
        }

        $state = Str::random(48);

        Cache::put($this->stateKey($state), [
            'agent_id' => $agent->id,
            'user_id' => auth()->id(),
        ], self::STATE_TTL);

        return redirect()->away($this->oauth->authorizeUrl($state, MetaOAuthClient::MESSENGER_SCOPES));
    }

    public function callback(Request $request): RedirectResponse
    {
        $state = Cache::pull($this->stateKey((string) $request->query('state')));

        if ($state === null || $state['user_id'] !== auth()->id()) {
            return redirect()->route('agents.index')->withErrors([
                'messenger' => "That Facebook connection expired or didn't match your session. Please try again.",
            ]);
        }

        $agent = Agent::find($state['agent_id']);
        $code = (string) $request->query('code');

        if ($agent === null || $code === '' || $request->query('error') !== null) {
            return $this->backToChannels($agent, 'Facebook sign-in was cancelled or failed. Please try again.');
        }

        $userToken = $this->oauth->exchangeCodeForUserToken($code);
        $pages = $userToken !== null ? $this->oauth->pages($userToken) : [];

        if ($pages === []) {
            return $this->backToChannels($agent, 'No Facebook Pages came back. Re-run the connection and make sure you select a Page and keep every permission checked.');
        }

        // Hold the tokens server-side; the picker only ever sees id + name.
        Cache::put($this->pagesKey($agent), $pages, self::STATE_TTL);

        return $this->backToChannels($agent);
    }

    public function select(Request $request, Agent $agent, ConnectMessengerPageAction $action): RedirectResponse
    {
        $pages = Cache::get($this->pagesKey($agent), []);
        $chosen = collect($pages)->firstWhere('id', (string) $request->input('page_id'));

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
        Cache::forget($this->pagesKey($agent));

        return back();
    }

    public function disconnect(Agent $agent): RedirectResponse
    {
        $agent->messengerPage?->delete();
        Cache::forget($this->pagesKey($agent));

        return back();
    }

    private function backToChannels(?Agent $agent, ?string $error = null): RedirectResponse
    {
        if ($agent === null) {
            return redirect()->route('agents.index');
        }

        $redirect = redirect()->route('agents.channels', $agent);

        return $error === null ? $redirect : $redirect->withErrors(['messenger' => $error]);
    }

    private function stateKey(string $state): string
    {
        return "meta:oauth:messenger:state:{$state}";
    }

    private function pagesKey(Agent $agent): string
    {
        return "meta:oauth:messenger:pages:{$agent->id}";
    }
}
