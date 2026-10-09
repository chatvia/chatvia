<?php

namespace App\Admin\Settings;

use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Route;
use LogicException;

/**
 * A set of credentials an admin manages together in Settings, usually one
 * third-party app or API key. Each field overrides one config key, so the code
 * that reads `config('services.…')` needs no change. The value is the
 * `admin_settings.key` of the group's row, so never rename a case value.
 */
enum SettingGroup: string
{
    case GoogleLogin = 'google-login';
    case MicrosoftLogin = 'microsoft-login';
    case GithubLogin = 'github-login';
    case SlackLogin = 'slack-login';

    case Meta = 'meta';

    case Scaleway = 'scaleway';
    case Mistral = 'mistral';
    case OpenRouter = 'openrouter';
    case Groq = 'groq';
    case Cohere = 'cohere';
    case Jina = 'jina';

    case Firecrawl = 'firecrawl';
    case ChunkFit = 'chunkfit';

    case GithubConnector = 'github-connector';
    case GmailConnector = 'gmail-connector';
    case GoogleCalendarConnector = 'google-calendar-connector';
    case GoogleDriveConnector = 'google-drive-connector';
    case SlackConnector = 'slack-connector';
    case HubspotConnector = 'hubspot-connector';
    case BoxConnector = 'box-connector';
    case AsanaConnector = 'asana-connector';

    case Stripe = 'stripe';

    case Resend = 'resend';
    case Turnstile = 'turnstile';
    case Ip2Location = 'ip2location';

    public function category(): SettingCategory
    {
        return match ($this) {
            self::GoogleLogin, self::MicrosoftLogin, self::GithubLogin, self::SlackLogin => SettingCategory::SignIn,
            self::Meta => SettingCategory::Channels,
            self::Scaleway, self::Mistral, self::OpenRouter, self::Groq, self::Cohere, self::Jina => SettingCategory::Ai,
            self::Firecrawl, self::ChunkFit => SettingCategory::Knowledge,
            self::Stripe => SettingCategory::Billing,
            self::Resend, self::Turnstile, self::Ip2Location => SettingCategory::Platform,
            default => SettingCategory::Connectors,
        };
    }

    /** Short name for the card on the Settings page. */
    public function label(): string
    {
        return match ($this) {
            self::GoogleLogin => 'Google',
            self::MicrosoftLogin => 'Microsoft',
            self::GithubLogin => 'GitHub',
            self::SlackLogin => 'Slack',
            self::Meta => 'Meta (Facebook)',
            self::Scaleway => 'Scaleway AI',
            self::Mistral => 'Mistral',
            self::OpenRouter => 'OpenRouter',
            self::Groq => 'Groq',
            self::Cohere => 'Cohere',
            self::Jina => 'Jina',
            self::Firecrawl => 'Firecrawl',
            self::ChunkFit => 'chunk.fit',
            self::Stripe => 'Stripe',
            self::Resend => 'Resend',
            self::Turnstile => 'Cloudflare Turnstile',
            self::Ip2Location => 'IP2Location',
            default => (string) config("connectors.{$this->connectorSlug()}.name"),
        };
    }

    /** Full name for the edit page, where the tab is not visible. */
    public function title(): string
    {
        return match ($this->category()) {
            SettingCategory::SignIn => "Sign in with {$this->label()}",
            SettingCategory::Connectors => "{$this->label()} connector",
            default => $this->label(),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::GoogleLogin => 'Google Cloud OAuth client for signing in with a Google account.',
            self::MicrosoftLogin => 'Microsoft Entra app registration for work and personal accounts.',
            self::GithubLogin => 'GitHub OAuth app for signing in with GitHub.',
            self::SlackLogin => 'Slack app for "Sign in with Slack".',
            self::Meta => 'The app that connects Facebook Pages, Instagram accounts and WhatsApp numbers.',
            self::Scaleway => 'Chat, Qwen3 embeddings, reranking and speech to text.',
            self::Mistral => 'Chat, document OCR and whole-PDF reading.',
            self::OpenRouter => 'Chat models from many providers through one key.',
            self::Groq => 'Fast chat models.',
            self::Cohere => 'Embeddings and reranking.',
            self::Jina => 'Embeddings.',
            self::Firecrawl => 'Imports websites as knowledge sources.',
            self::ChunkFit => 'Hosted text splitter for documents.',
            self::Stripe => 'Payments, subscriptions and the billing webhook.',
            self::Resend => 'Sends email when MAIL_MAILER is resend.',
            self::Turnstile => 'Bot check on the public contact form.',
            self::Ip2Location => 'Finds the country of a visitor from their IP address.',
            default => "OAuth app that lets workspaces connect {$this->label()} to their agents.",
        };
    }

    /**
     * A brand logo (an image URL) when the app ships one, otherwise a Heroicon.
     */
    public function icon(): string|Heroicon
    {
        return match ($this) {
            self::GoogleLogin => asset('images/logos/brands/google.svg'),
            self::MicrosoftLogin => asset('images/llms/microsoft.svg'),
            self::GithubLogin => asset('images/logos/connectors/github.svg'),
            self::SlackLogin => asset('images/logos/connectors/slack.svg'),
            self::Meta => asset('images/logos/brands/facebook.svg'),
            self::Mistral => asset('images/llms/mistral.svg'),
            self::Cohere => asset('images/llms/cohere.svg'),
            self::Stripe => asset('images/logos/connectors/stripe.svg'),
            self::Scaleway => Heroicon::OutlinedCpuChip,
            self::OpenRouter => Heroicon::OutlinedArrowsRightLeft,
            self::Groq => Heroicon::OutlinedBolt,
            self::Jina => Heroicon::OutlinedCubeTransparent,
            self::Firecrawl => Heroicon::OutlinedFire,
            self::ChunkFit => Heroicon::OutlinedScissors,
            self::Resend => Heroicon::OutlinedEnvelope,
            self::Turnstile => Heroicon::OutlinedShieldCheck,
            self::Ip2Location => Heroicon::OutlinedMapPin,
            default => asset(ltrim((string) config("connectors.{$this->connectorSlug()}.logo_url"), '/')),
        };
    }

    /**
     * @return list<SettingField>
     */
    public function fields(): array
    {
        return match ($this) {
            self::GoogleLogin => $this->oauthLogin('google'),
            self::MicrosoftLogin => $this->oauthLogin('microsoft'),
            self::GithubLogin => $this->oauthLogin('github'),
            self::SlackLogin => $this->oauthLogin('slack'),
            self::Meta => [
                SettingField::text('app_id', 'App ID', 'services.meta.app_id', required: true),
                SettingField::secret('app_secret', 'App secret', 'services.meta.app_secret'),
                SettingField::secret('webhook_verify_token', 'Webhook verify token', 'services.meta.webhook_verify_token',
                    help: 'Any random string. Paste the same value in the webhook settings of the Meta app.'),
                SettingField::secret('whatsapp_register_pin', 'WhatsApp registration PIN', 'services.meta.whatsapp_register_pin',
                    required: false, rules: ['digits:6', 'not_in:000000'],
                    help: 'Six digits used to register connected WhatsApp numbers. Do not use 000000.'),
                SettingField::text('config_id', 'Login configuration ID', 'services.meta.config_id',
                    help: 'Facebook Login for Business configuration. Without it the classic permission request is used.'),
                SettingField::text('instagram_config_id', 'Instagram configuration ID', 'services.meta.instagram_config_id',
                    help: 'Uses the login configuration ID when empty.'),
                SettingField::text('whatsapp_config_id', 'WhatsApp configuration ID', 'services.meta.whatsapp_config_id',
                    help: 'Uses the login configuration ID when empty.'),
                SettingField::url('redirect', 'Messenger redirect URL', 'services.meta.redirect'),
                SettingField::url('instagram_redirect', 'Instagram redirect URL', 'services.meta.instagram_redirect'),
            ],
            self::Scaleway => [SettingField::secret('api_key', 'API key', 'services.scalewayai.api_key')],
            self::Mistral => [SettingField::secret('api_key', 'API key', 'services.mistral.api_key')],
            self::OpenRouter => [SettingField::secret('api_key', 'API key', 'services.openrouter.api_key')],
            self::Groq => [SettingField::secret('api_key', 'API key', 'services.groq.api_key')],
            self::Cohere => [SettingField::secret('api_key', 'API key', 'services.cohere.api_key')],
            self::Jina => [SettingField::secret('api_key', 'API key', 'services.jina.api_key')],
            self::Firecrawl => [SettingField::secret('api_key', 'API key', 'services.firecrawl.key')],
            self::ChunkFit => [SettingField::secret('api_key', 'API key', 'services.chunkfit.api_key')],
            self::Stripe => [
                SettingField::text('key', 'Publishable key', 'cashier.key', required: true, rules: ['starts_with:pk_']),
                SettingField::secret('secret', 'Secret key', 'cashier.secret', rules: ['starts_with:sk_,rk_'],
                    help: 'A secret key (sk_) or a restricted key (rk_).'),
                SettingField::secret('webhook_secret', 'Webhook signing secret', 'cashier.webhook.secret', rules: ['starts_with:whsec_'],
                    help: 'With billing on, the app refuses to start in production without it.'),
            ],
            self::Resend => [SettingField::secret('api_key', 'API key', 'services.resend.key')],
            self::Turnstile => [
                SettingField::text('site_key', 'Site key', 'services.turnstile.key', required: true),
                SettingField::secret('secret_key', 'Secret key', 'services.turnstile.secret'),
            ],
            self::Ip2Location => [SettingField::secret('api_key', 'API key', 'services.ip2location.key')],
            default => [
                SettingField::text('client_id', 'Client ID', "connectors.{$this->connectorSlug()}.oauth_client_id", required: true),
                SettingField::secret('client_secret', 'Client secret', "connectors.{$this->connectorSlug()}.oauth_client_secret"),
            ],
        };
    }

    /**
     * Where the admin creates these credentials.
     */
    public function consoleUrl(): ?string
    {
        return match ($this) {
            self::GoogleLogin, self::GmailConnector, self::GoogleCalendarConnector, self::GoogleDriveConnector => 'https://console.cloud.google.com/apis/credentials',
            self::MicrosoftLogin => 'https://portal.azure.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade',
            self::GithubLogin, self::GithubConnector => 'https://github.com/settings/developers',
            self::SlackLogin, self::SlackConnector => 'https://api.slack.com/apps',
            self::Meta => 'https://developers.facebook.com/apps/',
            self::Scaleway => 'https://console.scaleway.com/iam/api-keys',
            self::Mistral => 'https://console.mistral.ai/api-keys',
            self::OpenRouter => 'https://openrouter.ai/settings/keys',
            self::Groq => 'https://console.groq.com/keys',
            self::Cohere => 'https://dashboard.cohere.com/api-keys',
            self::Firecrawl => 'https://www.firecrawl.dev/app/api-keys',
            self::BoxConnector => 'https://app.box.com/developers/console',
            self::AsanaConnector => 'https://app.asana.com/0/my-apps',
            self::Stripe => 'https://dashboard.stripe.com/apikeys',
            self::Resend => 'https://resend.com/api-keys',
            self::Turnstile => 'https://dash.cloudflare.com/?to=/:account/turnstile',
            self::Ip2Location => 'https://www.ip2location.io/dashboard',
            default => null,
        };
    }

    /**
     * URLs of this app that must be registered with the provider (OAuth
     * redirects, webhooks), keyed by label.
     *
     * @return array<string, string>
     */
    public function callbackUrls(): array
    {
        $urls = match ($this) {
            self::GoogleLogin => ['Redirect URL' => 'auth.google.callback'],
            self::MicrosoftLogin => ['Redirect URL' => 'auth.microsoft.callback'],
            self::GithubLogin => ['Callback URL' => 'auth.github.callback'],
            self::SlackLogin => ['Redirect URL' => 'auth.slack.callback'],
            self::Meta => [
                'Messenger OAuth redirect' => 'channels.messenger.callback',
                'Instagram OAuth redirect' => 'channels.instagram.callback',
                'Messenger webhook' => 'messenger.webhook',
                'Instagram webhook' => 'instagram.webhook',
                'WhatsApp webhook' => 'whatsapp.webhook',
            ],
            self::Stripe => ['Webhook endpoint' => 'cashier.webhook'],
            default => $this->category() === SettingCategory::Connectors ? ['Redirect URL' => 'connectors.oauth.callback'] : [],
        };

        // Cashier's webhook route only exists while billing is on.
        return array_map(
            fn (string $name): string => route($name),
            array_filter($urls, fn (string $name): bool => Route::has($name)),
        );
    }

    /**
     * @return list<SettingField>
     */
    public function requiredFields(): array
    {
        return array_values(array_filter($this->fields(), fn (SettingField $field): bool => $field->required));
    }

    /**
     * The `config/connectors.php` entry behind a connector group. The other
     * groups must be handled by name in the `match` that called this.
     */
    private function connectorSlug(): string
    {
        return match ($this) {
            self::GithubConnector => 'github',
            self::GmailConnector => 'gmail',
            self::GoogleCalendarConnector => 'google-calendar',
            self::GoogleDriveConnector => 'google-drive',
            self::SlackConnector => 'slack',
            self::HubspotConnector => 'hubspot',
            self::BoxConnector => 'box',
            self::AsanaConnector => 'asana',
            default => throw new LogicException("Settings group [{$this->value}] is not a connector."),
        };
    }

    /**
     * @return list<SettingField>
     */
    private function oauthLogin(string $service): array
    {
        return [
            SettingField::text('client_id', 'Client ID', "services.{$service}.client_id", required: true),
            SettingField::secret('client_secret', 'Client secret', "services.{$service}.client_secret"),
            SettingField::url('redirect', 'Redirect URL', "services.{$service}.redirect",
                help: 'Must match a redirect URL registered in the OAuth app.'),
        ];
    }
}
