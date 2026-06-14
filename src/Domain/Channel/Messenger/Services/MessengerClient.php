<?php

namespace Domain\Channel\Messenger\Services;

use Domain\Channel\Services\MetaGraphClient;
use Domain\Channel\Services\PlainTextFormatter;

/**
 * Thin wrapper over the Messenger Platform (Meta Graph API — plain HTTP, no
 * SDK), called per page access token (Chatvia is multi-page — one Facebook
 * Page per agent). Every call is best-effort: a Meta outage logs and returns
 * a safe value rather than throwing into the webhook/job path (see
 * {@see MetaGraphClient}).
 *
 * Messenger messages can't be edited, so replies don't stream — the adapter
 * keeps the typing indicator alive during generation and sends the full
 * reply at the end (chunked at Messenger's 2000-char limit).
 */
class MessengerClient extends MetaGraphClient
{
    private const GRAPH = 'https://graph.facebook.com/v25.0';

    /** Messenger caps a single text message at 2000 chars. */
    private const MESSAGE_LIMIT = 2000;

    /**
     * Validate a page access token and return the page's identity, or null if
     * invalid.
     *
     * @return array{id: ?string, name: ?string}|null
     */
    public function pageInfo(string $token): ?array
    {
        $response = $this->call($token, 'get', '/me', ['fields' => 'id,name']);

        if ($response === null || ! isset($response['id'])) {
            return null;
        }

        return [
            'id' => (string) $response['id'],
            'name' => $response['name'] ?? null,
        ];
    }

    /**
     * Subscribe the Meta app to the page's `messages` webhook field — without
     * this, Meta never delivers the page's messages to the Callback URL.
     * Best-effort (the workspace can also do it in the Meta dashboard).
     */
    public function subscribePage(string $token, string $pageId): bool
    {
        return $this->call($token, 'post', "/{$pageId}/subscribed_apps", [
            'subscribed_fields' => 'messages',
        ]) !== null;
    }

    /**
     * Look up a messaging user's first name (used once, to title the Chatvia
     * conversation). Best-effort — profile access can be restricted.
     *
     * @return array{first_name: ?string}|null
     */
    public function userProfile(string $token, string $psid): ?array
    {
        $response = $this->call($token, 'get', "/{$psid}", ['fields' => 'first_name']);

        if ($response === null) {
            return null;
        }

        return ['first_name' => $response['first_name'] ?? null];
    }

    /**
     * Send a reply (the model's Markdown stripped to plain text — see
     * {@see PlainTextFormatter}), chunked at the 2000-char limit.
     */
    public function sendMessage(string $token, string $psid, string $markdown): void
    {
        $text = (new PlainTextFormatter)->toPlain($markdown);

        foreach ($this->chunk($text) as $part) {
            $sent = $this->call($token, 'post', '/me/messages', [
                'recipient' => ['id' => $psid],
                'messaging_type' => 'RESPONSE',
                'message' => ['text' => $part],
            ]);

            if ($sent === null) {
                return;
            }
        }
    }

    /**
     * Show the "typing…" indicator in the thread. Messenger clears it after
     * ~20s (or when the next message arrives), so callers re-send it during a
     * long generation to keep it alive. Best-effort.
     */
    public function sendTypingAction(string $token, string $psid): void
    {
        $this->call($token, 'post', '/me/messages', [
            'recipient' => ['id' => $psid],
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
        return 'Messenger';
    }
}
