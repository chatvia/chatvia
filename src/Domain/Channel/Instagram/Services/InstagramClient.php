<?php

namespace Domain\Channel\Instagram\Services;

use Domain\Channel\Services\MetaGraphClient;
use Domain\Channel\Services\PlainTextFormatter;

/**
 * Thin wrapper over the Instagram Messaging API (Instagram Login flavour —
 * `graph.instagram.com`, no Facebook Page; plain HTTP, no SDK), called per
 * account access token (Chatvia is multi-account — one Instagram professional
 * account per agent). Every call is best-effort: a Meta outage logs and
 * returns a safe value rather than throwing into the webhook/job path (see
 * {@see MetaGraphClient}).
 *
 * Instagram messages can't be edited, so replies don't stream — the adapter
 * keeps the typing indicator alive during generation and sends the full
 * reply at the end (chunked at Instagram's 1000-char limit).
 */
class InstagramClient extends MetaGraphClient
{
    private const GRAPH = 'https://graph.instagram.com/v23.0';

    /** Instagram caps a single text message at 1000 chars. */
    private const MESSAGE_LIMIT = 1000;

    /**
     * Validate an access token and return the account's identity, or null if
     * invalid. `user_id` is the professional account's Instagram id — the one
     * that appears as sender/recipient in messaging webhooks.
     *
     * @return array{id: ?string, username: ?string}|null
     */
    public function accountInfo(string $token): ?array
    {
        $response = $this->call($token, 'get', '/me', ['fields' => 'user_id,username']);

        if ($response === null || ! isset($response['user_id'])) {
            return null;
        }

        return [
            'id' => (string) $response['user_id'],
            'username' => $response['username'] ?? null,
        ];
    }

    /**
     * Subscribe the Instagram app to the account's `messages` webhook field —
     * without this, Meta never delivers the account's DMs to the Callback URL.
     * Best-effort (the workspace can also do it in the Meta dashboard).
     */
    public function subscribeAccount(string $token): bool
    {
        return $this->call($token, 'post', '/me/subscribed_apps', [
            'subscribed_fields' => 'messages',
        ]) !== null;
    }

    /**
     * Look up a messaging user's profile (used once, to title the Chatvia
     * conversation). Best-effort — profile access can be restricted.
     *
     * @return array{name: ?string, username: ?string}|null
     */
    public function userProfile(string $token, string $igsid): ?array
    {
        $response = $this->call($token, 'get', "/{$igsid}", ['fields' => 'name,username']);

        if ($response === null) {
            return null;
        }

        return [
            'name' => $response['name'] ?? null,
            'username' => $response['username'] ?? null,
        ];
    }

    /**
     * Send a reply (the model's Markdown stripped to plain text — see
     * {@see PlainTextFormatter}), chunked at the 1000-char limit.
     */
    public function sendMessage(string $token, string $igsid, string $markdown): void
    {
        $text = (new PlainTextFormatter)->toPlain($markdown);

        foreach ($this->chunk($text) as $part) {
            $sent = $this->call($token, 'post', '/me/messages', [
                'recipient' => ['id' => $igsid],
                'message' => ['text' => $part],
            ]);

            if ($sent === null) {
                return;
            }
        }
    }

    /**
     * Show the "typing…" indicator in the thread. Instagram clears it after
     * ~20s (or when the next message arrives), so callers re-send it during a
     * long generation to keep it alive. Best-effort.
     */
    public function sendTypingAction(string $token, string $igsid): void
    {
        $this->call($token, 'post', '/me/messages', [
            'recipient' => ['id' => $igsid],
            'sender_action' => 'typing_on',
        ]);
    }

    protected function graphBaseUrl(): string
    {
        return self::GRAPH;
    }

    protected function messageLimit(): int
    {
        return self::MESSAGE_LIMIT;
    }

    protected function logLabel(): string
    {
        return 'Instagram';
    }
}
