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
 * today; Instagram reuses it via {@see pagesWithInstagram()} (a Page-linked IG
 * professional account, same Page token); WhatsApp reuses the token exchange
 * with its own embedded-signup specifics. Per-channel `configId`/`redirectUri`
 * overrides let each channel use its own Login-for-Business configuration and
 * callback URL. Every call is best-effort — a Meta hiccup logs and returns a
 * safe empty/null value rather than throwing.
 */
class MetaOAuthClient
{
    /** Page messaging + read content (the GET /me check) + manage webhooks + list pages. */
    public const MESSENGER_SCOPES = 'pages_show_list,pages_messaging,pages_read_engagement,pages_manage_metadata';

    /**
     * Instagram messaging via Facebook Login: read the IG professional account
     * linked to a Page, send/receive its DMs, list + manage the Pages. (When a
     * Login-for-Business config_id is used these come from the configuration;
     * this string is the classic-scope fallback.)
     */
    public const INSTAGRAM_SCOPES = 'instagram_basic,instagram_manage_messages,pages_show_list,pages_manage_metadata,pages_read_engagement';

    /**
     * Build the Facebook authorize URL. Uses the Login-for-Business `config_id`
     * when configured (permissions come from the configuration); otherwise it
     * falls back to a classic `scope` request. `configId`/`redirectUri` let a
     * channel override the shared defaults (Instagram has its own callback).
     */
    public function authorizeUrl(string $state, string $scopes, ?string $configId = null, ?string $redirectUri = null): string
    {
        $params = [
            'client_id' => (string) config('services.meta.app_id'),
            'redirect_uri' => (string) ($redirectUri ?: config('services.meta.redirect')),
            'state' => $state,
            'response_type' => 'code',
        ];

        $configId ??= config('services.meta.config_id');

        if (is_string($configId) && $configId !== '') {
            $params['config_id'] = $configId;
        } else {
            $params['scope'] = $scopes;
        }

        return 'https://www.facebook.com/'.$this->version().'/dialog/oauth?'.http_build_query($params);
    }

    /**
     * Swap the OAuth code for a long-lived user access token (~60 days), or
     * null on failure. The `redirectUri` MUST match the one used in
     * {@see authorizeUrl()}. Page tokens derived from a long-lived user token
     * are themselves long-lived, so the stored page token effectively never
     * expires.
     */
    public function exchangeCodeForUserToken(string $code, ?string $redirectUri = null): ?string
    {
        $short = $this->get('/oauth/access_token', [
            'client_id' => (string) config('services.meta.app_id'),
            'client_secret' => (string) config('services.meta.app_secret'),
            'redirect_uri' => (string) ($redirectUri ?: config('services.meta.redirect')),
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

    /**
     * List the Pages the user manages that have a linked Instagram professional
     * account, each carrying its own page access token plus the IG account's id
     * + username. Pages without a linked IG account are omitted — they can't
     * power an Instagram channel. The page access token is what sends IG DMs
     * (graph.facebook.com/me/messages), so it travels with each entry.
     *
     * Two hops: /me/accounts (pages + tokens), then per page
     * /{page-id}?fields=instagram_business_account{id,username}.
     *
     * @return list<array{page_id: string, page_name: string, page_access_token: string, ig_id: string, ig_username: string}>
     */
    public function pagesWithInstagram(string $userToken): array
    {
        $out = [];

        foreach ($this->pages($userToken) as $page) {
            $linked = $this->get('/'.$page['id'], [
                'fields' => 'instagram_business_account{id,username}',
                'access_token' => $page['access_token'],
            ]);

            $iba = $linked['instagram_business_account'] ?? null;
            $igId = is_array($iba) ? ($iba['id'] ?? null) : null;

            if (! is_string($igId) || $igId === '') {
                continue; // no IG professional account linked to this Page
            }

            $out[] = [
                'page_id' => $page['id'],
                'page_name' => $page['name'],
                'page_access_token' => $page['access_token'],
                'ig_id' => $igId,
                'ig_username' => is_array($iba) ? (string) ($iba['username'] ?? '') : '',
            ];
        }

        return $out;
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
