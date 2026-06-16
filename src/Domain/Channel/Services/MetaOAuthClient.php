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
    /**
     * Page messaging + read content (the GET /me check) + manage webhooks + list
     * pages. `business_management` is required to see Pages owned by a Business
     * Portfolio — `/me/accounts` omits those (it only lists personally-managed
     * Pages), so without it a business customer's Page never comes back.
     */
    public const MESSENGER_SCOPES = 'pages_show_list,pages_messaging,pages_read_engagement,pages_manage_metadata,business_management';

    /**
     * Instagram messaging via Facebook Login: read the IG professional account
     * linked to a Page, send/receive its DMs, list + manage the Pages, plus
     * `business_management` for Business-Portfolio-owned Pages. (When a
     * Login-for-Business config_id is used these come from the configuration;
     * this string is the classic-scope fallback.)
     */
    public const INSTAGRAM_SCOPES = 'instagram_basic,instagram_manage_messages,pages_show_list,pages_manage_metadata,pages_read_engagement,business_management';

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
        /** @var array<string, array{id: string, name: string, access_token: string}> $byId */
        $byId = [];

        // 1. Pages the user manages personally.
        $this->collectPages($this->get('/me/accounts', [
            'fields' => 'id,name,access_token',
            'limit' => 100,
            'access_token' => $userToken,
        ]), $byId);

        $personalCount = count($byId);

        // 2. Pages owned by the user's Business Portfolio(s). `/me/accounts`
        //    omits these — they only surface by traversing the business, which
        //    needs the `business_management` permission. owned_pages/client_pages
        //    return the page access token directly (fields=access_token).
        $businesses = $this->get('/me/businesses', [
            'fields' => 'id,name',
            'limit' => 100,
            'access_token' => $userToken,
        ]);

        $businessIds = [];

        foreach ((array) ($businesses['data'] ?? []) as $business) {
            $bizId = $business['id'] ?? null;

            if (! is_string($bizId)) {
                continue;
            }

            $businessIds[] = $bizId;

            $this->collectPages($this->get("/{$bizId}/owned_pages", [
                'fields' => 'id,name,access_token',
                'limit' => 100,
                'access_token' => $userToken,
            ]), $byId);

            $this->collectPages($this->get("/{$bizId}/client_pages", [
                'fields' => 'id,name,access_token',
                'limit' => 100,
                'access_token' => $userToken,
            ]), $byId);
        }

        $pages = array_values($byId);

        $context = [
            'personal_pages' => $personalCount,
            'businesses' => $businessIds,
            'business_response_present' => $businesses !== null,
            'total_pages' => count($pages),
            'page_ids' => array_map(static fn (array $p): string => $p['id'], $pages), // ids only, never tokens
        ];

        if ($pages === []) {
            // The "No Pages came back" case — warning level so it shows up
            // regardless of LOG_LEVEL. If `businesses` is empty/null the
            // `business_management` grant is the thing to check.
            Log::warning('Meta OAuth: no Pages from /me/accounts or any Business Portfolio', $context);
        } else {
            Log::info('Meta OAuth: Pages resolved (personal + business)', $context);
        }

        return $pages;
    }

    /**
     * Merge a Graph `data[]` of pages into the id-keyed accumulator (first write
     * wins, so a personally-managed token is preferred over a business one for
     * the same Page).
     *
     * @param  array<string, mixed>|null  $response
     * @param  array<string, array{id: string, name: string, access_token: string}>  $byId
     */
    private function collectPages(?array $response, array &$byId): void
    {
        foreach ((array) ($response['data'] ?? []) as $page) {
            $id = $page['id'] ?? null;
            $token = $page['access_token'] ?? null;

            if (is_string($id) && is_string($token) && ! isset($byId[$id])) {
                $byId[$id] = [
                    'id' => $id,
                    'name' => (string) ($page['name'] ?? $id),
                    'access_token' => $token,
                ];
            }
        }
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
