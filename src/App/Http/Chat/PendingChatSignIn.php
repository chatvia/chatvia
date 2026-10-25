<?php

declare(strict_types=1);

namespace App\Http\Chat;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ChatViewerKind;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * A sign-in link's account, waiting for the person to confirm the switch.
 *
 * A sign-in link (`?token=`, `?auth_token=`, an e-mailed magic link) signs in
 * whoever it was made for. When the browser is already signed in to the chat
 * as someone else, following it must not swap the account silently: anyone
 * could send a link to their own account and have the victim chat as them
 * (login CSRF). So the link's account is kept here, in the chat session, and
 * the person is asked "Continue as …?" on a page that switches only on a
 * CSRF-protected POST. A browser with nobody signed in is signed in at once,
 * as before.
 */
final readonly class PendingChatSignIn
{
    private const string SESSION_KEY = 'chat_sign_in.pending';

    private const int LIFETIME_MINUTES = 10;

    private function __construct(
        public Chatter|User $account,
        public string $returnTo,
    ) {}

    /** Whether signing $account in would replace a different signed-in account. */
    public static function wouldReplace(Chatter|User $account): bool
    {
        $current = HostedChatViewer::account();

        return $current !== null && ! $current->is($account);
    }

    /**
     * Keep the link's account for the confirm step and send the browser
     * there. After the switch it returns to the address the link opened,
     * without its sign-in token.
     */
    public static function hold(Request $request, Chatter|User $account, Agent $agent): RedirectResponse
    {
        $query = Arr::except($request->query(), ['token', 'auth_token']);

        $request->session()->put(self::SESSION_KEY, [
            'kind' => $account instanceof Chatter ? ChatViewerKind::Chatter->value : ChatViewerKind::Staff->value,
            'id' => $account->id,
            'agent_id' => $agent->id,
            'return_to' => '/'.$request->path().(filled($query) ? '?'.Arr::query($query) : ''),
            'expires_at' => now()->addMinutes(self::LIFETIME_MINUTES)->getTimestamp(),
        ]);

        return to_route('chat.switch-account.show');
    }

    /**
     * The account waiting for $agent's chat, or null when there is none, it
     * expired, or the account can no longer reach the agent.
     */
    public static function find(Request $request, Agent $agent): ?self
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        if (! is_array($pending) || (int) ($pending['agent_id'] ?? 0) !== $agent->id || (int) ($pending['expires_at'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        $account = match (ChatViewerKind::tryFrom((string) ($pending['kind'] ?? ''))) {
            ChatViewerKind::Chatter => Chatter::query()->where('workspace_id', $agent->workspace_id)->whereKey($pending['id'] ?? 0)->first(),
            ChatViewerKind::Staff => User::query()->whereKey($pending['id'] ?? 0)->first(),
            default => null,
        };

        if ($account === null || $account->isDeactivated() || ! $account->canAccessAgent($agent)) {
            return null;
        }

        return new self($account, (string) ($pending['return_to'] ?? ''));
    }

    /** Drop the waiting account. */
    public static function forget(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }
}
