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
 *
 * The whole flow is heavily logged (channel "META_OAUTH") so a "no Pages came
 * back" can be diagnosed from the production log alone: never log a token or
 * the app secret — only their presence/length and (for Pages) ids + names.
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
        $appId = (string) config('services.meta.app_id');
        $redirect = (string) ($redirectUri ?: config('services.meta.redirect'));
        $configId ??= config('services.meta.config_id');
        $usingConfig = is_string($configId) && $configId !== '';

        $params = [
            'client_id' => $appId,
            'redirect_uri' => $redirect,
            'state' => $state,
            'response_type' => 'code',
        ];

        if ($usingConfig) {
            $params['config_id'] = (string) $configId;
        } else {
            $params['scope'] = $scopes;
        }

        // app_id / redirect / config_id are all public (they appear in the URL).
        Log::info('Meta OAuth: built authorize URL', [
            'has_app_id' => $appId !== '',
            'app_id' => $appId,
            'redirect_uri' => $redirect,
            'mode' => $usingConfig ? 'config_id' : 'scope',
            'config_id' => $usingConfig ? (string) $configId : null,
            'scopes' => $usingConfig ? null : $scopes,
            'graph_version' => $this->version(),
        ]);

        return 'https://www.facebook.com/'.$this->version().'/dialog/oauth?'.http_build_query($params);
    }

    /**
     * Swap the OAuth code for a long-lived user access token (~60 days), or
     * null on failure. The `redirectUri` MUST match the one used in
     * {@see authorizeUrl()}.
     */
    public function exchangeCodeForUserToken(string $code, ?string $redirectUri = null): ?string
    {
        $appId = (string) config('services.meta.app_id');
        $appSecret = (string) config('services.meta.app_secret');
        $redirect = (string) ($redirectUri ?: config('services.meta.redirect'));

        Log::info('Meta OAuth: exchanging code for user token', [
            'has_app_id' => $appId !== '',
            'has_app_secret' => $appSecret !== '',
            'app_secret_len' => strlen($appSecret),
            'redirect_uri' => $redirect,
            'code_len' => strlen($code),
        ]);

        $short = $this->get('/oauth/access_token', [
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'redirect_uri' => $redirect,
            'code' => $code,
        ]);

        $shortToken = $short['access_token'] ?? null;

        if (! is_string($shortToken)) {
            Log::warning('Meta OAuth: code → short-lived token exchange returned no access_token (check app secret + redirect_uri match)');

            return null;
        }

        Log::info('Meta OAuth: got short-lived user token', ['len' => strlen($shortToken)]);

        $long = $this->get('/oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'fb_exchange_token' => $shortToken,
        ]);

        $longToken = $long['access_token'] ?? null;

        Log::info('Meta OAuth: short → long-lived token exchange', ['got_long_lived' => is_string($longToken)]);

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

        $context = [
            'response_present' => $response !== null,
            'top_keys' => $response !== null ? array_keys($response) : [],
            'data_count' => is_array($response['data'] ?? null) ? count($response['data']) : 0,
            'usable_pages' => count($pages),
            'raw' => $this->redactTokens($response),
        ];

        if ($pages === []) {
            // The "No Pages came back" case — log the full (redacted) /me/accounts
            // body at warning level so it's visible regardless of LOG_LEVEL.
            Log::warning('Meta OAuth: /me/accounts returned no usable Pages', $context);
        } else {
            Log::info('Meta OAuth: /me/accounts returned Pages', $context);
        }

        return $pages;
    }

    /**
     * List the Pages the user manages that have a linked Instagram professional
     * account, each carrying its own page access token plus the IG account's id
     * + username. Pages without a linked IG account are omitted.
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

            Log::info('Meta OAuth: resolved IG account for Page', [
                'page_id' => $page['id'],
                'page_name' => $page['name'],
                'has_ig_account' => is_string($igId) && $igId !== '',
                'ig_id' => is_string($igId) ? $igId : null,
            ]);

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

        Log::info('Meta OAuth: IG-linked accounts resolved', ['count' => count($out)]);

        return $out;
    }

    private function version(): string
    {
        return (string) config('services.meta.graph_version', 'v25.0');
    }

    /**
     * Replace any `access_token` values in a Graph response with a length
     * marker so the body can be logged safely.
     *
     * @param  array<string, mixed>|null  $response
     * @return array<string, mixed>|null
     */
    private function redactTokens(?array $response): ?array
    {
        if ($response === null) {
            return null;
        }

        if (is_array($response['data'] ?? null)) {
            $response['data'] = array_map(static function ($page) {
                if (is_array($page) && isset($page['access_token']) && is_string($page['access_token'])) {
                    $page['access_token'] = '[redacted len='.strlen($page['access_token']).']';
                }

                return $page;
            }, $response['data']);
        }

        return $response;
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
                // The full error array (message/type/code/error_subcode/fbtrace_id)
                // is the gold for diagnosing a refusal — it contains no secrets.
                Log::warning("Meta OAuth {$path} returned an error", [
                    'status' => $response->status(),
                    'error' => is_array($json) ? ($json['error'] ?? 'no error key in body') : 'non-array body',
                ]);

                return null;
            }

            return $json;
        } catch (Throwable $e) {
            Log::warning("Meta OAuth {$path} failed", ['error' => $e->getMessage()]);

            return null;
        }
    }
}
