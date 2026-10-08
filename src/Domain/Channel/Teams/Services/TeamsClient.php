<?php

namespace Domain\Channel\Teams\Services;

use Domain\Channel\Services\RedactedException;
use Domain\Channel\Teams\Models\AgentTeamsBot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin wrapper over the Bot Framework connector REST API (plain HTTP — no
 * SDK), called per bot (Chatvia is multi-bot — one Azure bot per agent).
 * Outbound calls authenticate with a client-credentials token from Entra
 * (cached until shortly before expiry). Every call is best-effort: an outage
 * logs and returns a safe value rather than throwing into the webhook/job
 * path.
 *
 * Replies stream: {@see postMessage} drops a placeholder and returns its
 * activity id, then {@see updateMessage} rewrites it in place as the agent
 * generates. All calls go to the per-conversation `serviceUrl` that arrived
 * on the inbound activity — it varies by region and tenant.
 */
class TeamsClient
{
    /** Teams rejects activities whose total payload exceeds ~28 KB. */
    private const MESSAGE_LIMIT = 25000;

    private const TOKEN_SCOPE = 'https://api.botframework.com/.default';

    /**
     * Validate the bot's credentials by requesting a Bot Framework token —
     * the closest Teams analog of Slack's auth.test. False when Entra rejects
     * them (or is unreachable).
     */
    public function validateCredentials(string $appId, string $appSecret, ?string $tenantId): bool
    {
        return $this->requestToken($appId, $appSecret, $tenantId) !== null;
    }

    /**
     * Post a message (the model's Markdown — Teams renders it natively, see
     * {@see TeamsFormatter}) and return its activity id, the handle later
     * {@see updateMessage} calls stream into.
     */
    public function postMessage(AgentTeamsBot $bot, string $serviceUrl, string $conversationId, string $markdown): ?string
    {
        $text = $this->render($markdown);

        if ($text === '') {
            return null;
        }

        $response = $this->call($bot, 'post', $this->activitiesUrl($serviceUrl, $conversationId), [
            'type' => 'message',
            'textFormat' => 'markdown',
            'text' => $text,
        ]);

        $id = $response['id'] ?? null;

        return is_string($id) ? $id : null;
    }

    /**
     * Rewrite a previously posted activity in place — this is what makes the
     * reply visibly stream in Teams.
     */
    public function updateMessage(AgentTeamsBot $bot, string $serviceUrl, string $conversationId, string $activityId, string $markdown): bool
    {
        $text = $this->render($markdown);

        if ($text === '') {
            return false;
        }

        return $this->call($bot, 'put', $this->activitiesUrl($serviceUrl, $conversationId).'/'.rawurlencode($activityId), [
            'type' => 'message',
            'textFormat' => 'markdown',
            'text' => $text,
            'id' => $activityId,
        ]) !== null;
    }

    /**
     * Call the connector API; null on transport failure or a non-2xx reply.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    protected function call(AgentTeamsBot $bot, string $method, string $url, array $payload): ?array
    {
        $token = $this->token($bot);

        if ($token === null) {
            return null;
        }

        try {
            $response = Http::withToken($token)->asJson()->{$method}($url, $payload);
        } catch (Throwable $e) {
            Log::warning('Teams connector call failed', ['url' => $url, ...RedactedException::context($e)]);

            return null;
        }

        if ($response->failed()) {
            Log::warning('Teams connector returned an error', ['url' => $url, 'status' => $response->status()]);

            return null;
        }

        return $response->json() ?? [];
    }

    /**
     * The bot's outbound Bot Framework token, fetched with client credentials
     * and cached until shortly before it expires (~1 h). Null when Entra
     * rejects the credentials or is unreachable — never cached, so a transient
     * failure recovers on the next message.
     */
    protected function token(AgentTeamsBot $bot): ?string
    {
        $key = "msteams:token:{$bot->id}";
        $cached = Cache::get($key);

        if (is_string($cached)) {
            return $cached;
        }

        $grant = $this->requestToken($bot->app_id, $bot->app_secret, $bot->tenant_id);

        if ($grant === null) {
            return null;
        }

        Cache::put($key, $grant['access_token'], now()->addSeconds(max(60, (int) $grant['expires_in'] - 300)));

        return $grant['access_token'];
    }

    /**
     * Client-credentials grant against Entra. Multi-tenant bots authenticate
     * in the shared `botframework.com` tenant; single-tenant bots in their own.
     *
     * @return array{access_token: string, expires_in: int}|null
     */
    protected function requestToken(string $appId, string $appSecret, ?string $tenantId): ?array
    {
        $tenant = $tenantId ?? 'botframework.com';

        try {
            $response = Http::asForm()->post("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", [
                'grant_type' => 'client_credentials',
                'client_id' => $appId,
                'client_secret' => $appSecret,
                'scope' => self::TOKEN_SCOPE,
            ]);
        } catch (Throwable $e) {
            Log::warning('Teams token request failed', RedactedException::context($e));

            return null;
        }

        $token = $response->json('access_token');

        if ($response->failed() || ! is_string($token)) {
            Log::warning('Teams token request rejected', ['status' => $response->status(), 'error' => $response->json('error')]);

            return null;
        }

        return ['access_token' => $token, 'expires_in' => (int) $response->json('expires_in', 3600)];
    }

    /**
     * Conversation ids contain `:`/`;`/`@` and must be path-encoded.
     */
    private function activitiesUrl(string $serviceUrl, string $conversationId): string
    {
        return rtrim($serviceUrl, '/').'/v3/conversations/'.rawurlencode($conversationId).'/activities';
    }

    private function render(string $markdown): string
    {
        $text = (new TeamsFormatter)->toTeamsMarkdown($markdown);

        if (mb_strlen($text) > self::MESSAGE_LIMIT) {
            Log::warning('Teams message truncated at limit', ['length' => mb_strlen($text)]);
            $text = mb_substr($text, 0, self::MESSAGE_LIMIT - 1).'…';
        }

        return $text;
    }
}
