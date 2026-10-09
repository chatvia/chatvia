<?php

namespace Domain\Channel\Instagram\Services;

use Domain\Channel\Services\MetaGraphClient;
use Domain\Channel\Services\PlainTextFormatter;

/**
 * Thin wrapper over Instagram Messaging via Facebook Login — `graph.facebook.com`
 * with a Facebook PAGE access token (the Page linked to the IG professional
 * account). The send/typing payloads are the Messenger shape; `recipient.id` is
 * the IGSID from the webhook. Every call is best-effort: a Meta outage logs and
 * returns a safe value rather than throwing into the webhook/job path (see
 * {@see MetaGraphClient}).
 *
 * Instagram messages can't be edited, so replies don't stream — the adapter
 * keeps the typing indicator alive during generation and sends the full reply
 * at the end (chunked at Instagram's 1000-char limit).
 */
class InstagramClient extends MetaGraphClient
{
    /** Instagram caps a single text message at 1000 chars. */
    private const MESSAGE_LIMIT = 1000;

    /**
     * Subscribe the linked Page to its `messages` field — this transitively
     * subscribes the IG professional account's DMs to Chatvia's app-level
     * Callback URL. Best-effort (the workspace can also do it in the dashboard).
     */
    public function subscribeAccount(string $pageToken, string $pageId): bool
    {
        return $this->call($pageToken, 'post', "/{$pageId}/subscribed_apps", [
            'subscribed_fields' => 'messages',
        ]) !== null;
    }

    /**
     * Look up a messaging user's profile (used once, to title the Chatvia
     * conversation). Best-effort — profile access can be restricted.
     *
     * @return array{name: ?string, username: ?string}|null
     */
    public function userProfile(string $pageToken, string $igsid): ?array
    {
        $response = $this->call($pageToken, 'get', "/{$igsid}", ['fields' => 'name,username']);

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
    public function sendMessage(string $pageToken, string $igsid, string $markdown): void
    {
        $text = (new PlainTextFormatter)->toPlain($markdown);

        foreach ($this->chunk($text) as $part) {
            $sent = $this->call($pageToken, 'post', '/me/messages', [
                'recipient' => ['id' => $igsid],
                'messaging_type' => 'RESPONSE',
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
    public function sendTypingAction(string $pageToken, string $igsid): void
    {
        $this->call($pageToken, 'post', '/me/messages', [
            'recipient' => ['id' => $igsid],
            'sender_action' => 'typing_on',
        ]);
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
