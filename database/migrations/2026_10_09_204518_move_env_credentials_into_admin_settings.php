<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Third-party credentials now live only in the admin panel (Settings), not in
 * .env. This copies the values an install still has in .env into
 * `admin_settings`, so nothing stops working when the new code goes live.
 *
 * It reads .env directly, because the config files no longer do. That works
 * while migrating: the deploy runs `migrate` before `config:cache`. A value
 * already saved in the panel is never overwritten. After this has run, the
 * old .env lines are unused and can be deleted.
 */
return new class extends Migration
{
    /**
     * Settings group => field => the .env variable it used to come from (or
     * several, the first one set wins).
     *
     * @var array<string, array<string, string|list<string>>>
     */
    private array $map = [
        'google-login' => ['client_id' => 'GOOGLE_AUTH_CLIENT_ID', 'client_secret' => 'GOOGLE_AUTH_CLIENT_SECRET', 'redirect' => 'GOOGLE_AUTH_REDIRECT_URI'],
        'microsoft-login' => ['client_id' => 'MICROSOFT_CLIENT_ID', 'client_secret' => 'MICROSOFT_CLIENT_SECRET', 'redirect' => 'MICROSOFT_REDIRECT_URI'],
        'github-login' => ['client_id' => 'GITHUB_CLIENT_ID', 'client_secret' => 'GITHUB_CLIENT_SECRET', 'redirect' => 'GITHUB_REDIRECT_URI'],
        'slack-login' => ['client_id' => 'SLACK_CLIENT_ID', 'client_secret' => 'SLACK_CLIENT_SECRET', 'redirect' => 'SLACK_REDIRECT_URI'],
        // The one shared Meta app becomes the app of each channel. Instagram and
        // WhatsApp used the shared Login configuration when they had none.
        'messenger' => [
            'app_id' => 'META_APP_ID',
            'app_secret' => 'META_APP_SECRET',
            'webhook_verify_token' => 'META_WEBHOOK_VERIFY_TOKEN',
            'config_id' => 'META_LOGIN_CONFIG_ID',
            'redirect' => 'META_REDIRECT_URI',
        ],
        'instagram' => [
            'app_id' => 'META_APP_ID',
            'app_secret' => 'META_APP_SECRET',
            'webhook_verify_token' => 'META_WEBHOOK_VERIFY_TOKEN',
            'config_id' => ['META_INSTAGRAM_LOGIN_CONFIG_ID', 'META_LOGIN_CONFIG_ID'],
            'redirect' => 'META_INSTAGRAM_REDIRECT_URI',
        ],
        'whatsapp' => [
            'app_id' => 'META_APP_ID',
            'app_secret' => 'META_APP_SECRET',
            'webhook_verify_token' => 'META_WEBHOOK_VERIFY_TOKEN',
            'config_id' => ['META_WHATSAPP_LOGIN_CONFIG_ID', 'META_LOGIN_CONFIG_ID'],
            'register_pin' => 'META_WHATSAPP_REGISTER_PIN',
        ],
        'scaleway' => ['api_key' => 'SCALEWAYAI_API_KEY'],
        'mistral' => ['api_key' => 'MISTRAL_API_KEY'],
        'openrouter' => ['api_key' => 'OPENROUTER_API_KEY'],
        'groq' => ['api_key' => 'GROQ_API_KEY'],
        'cohere' => ['api_key' => 'COHERE_API_KEY'],
        'jina' => ['api_key' => 'JINA_API_KEY'],
        'firecrawl' => ['api_key' => 'FIRECRAWL_API_KEY'],
        'chunkfit' => ['api_key' => 'CHUNK_FIT_API_KEY'],
        'github-connector' => ['client_id' => 'GITHUB_OAUTH_CLIENT_ID', 'client_secret' => 'GITHUB_OAUTH_CLIENT_SECRET'],
        'gmail-connector' => ['client_id' => 'GOOGLE_CONNECTOR_OAUTH_CLIENT_ID', 'client_secret' => 'GOOGLE_CONNECTOR_OAUTH_CLIENT_SECRET'],
        'google-calendar-connector' => ['client_id' => 'GOOGLE_CALENDAR_CLIENT_ID', 'client_secret' => 'GOOGLE_CALENDAR_CLIENT_SECRET'],
        'google-drive-connector' => ['client_id' => 'GOOGLE_OAUTH_CLIENT_ID', 'client_secret' => 'GOOGLE_OAUTH_CLIENT_SECRET'],
        'slack-connector' => ['client_id' => 'SLACK_OAUTH_CLIENT_ID', 'client_secret' => 'SLACK_OAUTH_CLIENT_SECRET'],
        'hubspot-connector' => ['client_id' => 'HUBSPOT_OAUTH_CLIENT_ID', 'client_secret' => 'HUBSPOT_OAUTH_CLIENT_SECRET'],
        'box-connector' => ['client_id' => 'BOX_OAUTH_CLIENT_ID', 'client_secret' => 'BOX_OAUTH_CLIENT_SECRET'],
        'asana-connector' => ['client_id' => 'ASANA_OAUTH_CLIENT_ID', 'client_secret' => 'ASANA_OAUTH_CLIENT_SECRET'],
        'stripe' => ['key' => 'STRIPE_KEY', 'secret' => 'STRIPE_SECRET', 'webhook_secret' => 'STRIPE_WEBHOOK_SECRET'],
        'resend' => ['api_key' => 'RESEND_KEY'],
        'turnstile' => ['site_key' => 'TURNSTILE_SITE_KEY', 'secret_key' => 'TURNSTILE_SECRET_KEY'],
        'ip2location' => ['api_key' => 'IP2LOCATION_API_KEY'],
    ];

    public function up(): void
    {
        foreach ($this->map as $key => $fields) {
            $fromEnv = array_filter(
                array_map($this->firstSet(...), $fields),
                fn (string $value): bool => $value !== '',
            );

            if ($fromEnv === []) {
                continue;
            }

            $row = DB::table('admin_settings')->where('key', $key)->first();
            $saved = filled($row?->payload) ? json_decode(Crypt::decryptString($row->payload), true) : [];

            // What is already saved in the panel wins.
            $payload = Crypt::encryptString(json_encode([...$fromEnv, ...array_filter($saved, 'filled')]));

            DB::table('admin_settings')->updateOrInsert(
                ['key' => $key],
                ['payload' => $payload, 'created_at' => $row?->created_at ?? now(), 'updated_at' => now()],
            );
        }

        Cache::forget('admin_settings');
    }

    /**
     * @param  string|list<string>  $variables
     */
    private function firstSet(string|array $variables): string
    {
        foreach ((array) $variables as $variable) {
            $value = trim((string) env($variable));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    public function down(): void
    {
        // The values stay in admin_settings; the create migration's down()
        // drops the table.
    }
};
