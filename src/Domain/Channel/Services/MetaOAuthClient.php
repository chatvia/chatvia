<?php

namespace Domain\Channel\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * OAuth + token-exchange against Chatvia's own Meta app (one app for every
 * workspace — customers no longer create their own). It builds the Facebook
 * Login for Business authorize URL, swaps the returned code for a long-lived
 * user token, and lists the Pages (each carrying its own long-lived page
 * access token) the user just granted. Shared groundwork: Messenger uses it
 * today; Instagram is a near drop-in and WhatsApp reuses the token exchange
 * with its own embedded-signup specifics. Every call is best-effort — a Meta
 * hiccup logs and returns a safe empty/null value rather than throwing.
 */
class MetaOAuthClient
{
    /** Page messaging + read content (the GET /me check) + manage webhooks + list pages. */
    public const MESSENGER_SCOPES = 'pages_show_list,pages_messaging,pages_read_engagement,pages_manage_metadata';

    /**
     * Build the Facebook authorize URL. Uses the Login-for-Business `config_id`
     * when configured (permissions come from the configuration); otherwise it
     * falls back to a classic `scope` request.
     */
    public function authorizeUrl(string $state, string $scopes): string
    {
        $params = [
            'client_id' => (string) config('services.meta.app_id'),
            'redirect_uri' => (string) config('services.meta.redirect'),
            'state' => $state,
            'response_type' => 'code',
        ];

        $configId = config('services.meta.config_id');

        if (is_string($configId) && $configId !== '') {
            $params['config_id'] = $configId;
        } else {
            $params['scope'] = $scopes;
        }

        return 'https://www.facebook.com/'.$this->version().'/dialog/oauth?'.http_build_query($params);
    }

    /**
     * Swap the OAuth code for a long-lived user access token (~60 days), or
     * null on failure. Page tokens derived from a long-lived user token are
     * themselves long-lived, so the stored page token effectively never expires.
     */
    public function exchangeCodeForUserToken(string $code): ?string
    {
        $short = $this->get('/oauth/access_token', [
            'client_id' => (string) config('services.meta.app_id'),
            'client_secret' => (string) config('services.meta.app_secret'),
            'redirect_uri' => (string) config('services.meta.redirect'),
            'code' => $code,
        ]);

        $shortToken = $short['access_token'] ?? null;

        if (! is_string($shortToken)) {
            return null;
        }

        $long = $this->get('/oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => (string) config('services.meta.app_id'),
            'client_secret' => (string) config('services.meta.app_secret'),
            'fb_exchange_token' => $shortToken,
        ]);

        $longToken = $long['access_token'] ?? null;

        return is_string($longToken) ? $longToken : $shortToken;
    }

    /**
     * List the Pages the user manages, each with its own page access token.
     *
     * @return list<array{id: string, name: string, access_token: string}>
     */
    public function pages(string $userToken): array
    {
        $response = $this->get('/me/accounts', [
            'fields' => 'id,name,access_token',
            'limit' => 100,
            'access_token' => $userToken,
        ]);

        $pages = [];

        foreach ((array) ($response['data'] ?? []) as $page) {
            $id = $page['id'] ?? null;
            $token = $page['access_token'] ?? null;

            if (is_string($id) && is_string($token)) {
                $pages[] = [
                    'id' => $id,
                    'name' => (string) ($page['name'] ?? $id),
                    'access_token' => $token,
                ];
            }
        }

        return $pages;
    }

    private function version(): string
    {
        return (string) config('services.meta.graph_version', 'v25.0');
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    private function get(string $path, array $query): ?array
    {
        try {
            $response = Http::get('https://graph.facebook.com/'.$this->version().$path, $query);
            $json = $response->json();

            if (! $response->successful() || ! is_array($json) || isset($json['error'])) {
                Log::warning("Meta OAuth {$path} returned an error", ['error' => $json['error']['message'] ?? 'malformed response']);

                return null;
            }

            return $json;
        } catch (Throwable $e) {
            Log::warning("Meta OAuth {$path} failed", ['error' => $e->getMessage()]);

            return null;
        }
    }
}
