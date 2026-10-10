<?php

namespace App\Admin\Settings;

use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Route;
use LogicException;
use NumberFormatter;

/**
 * A set of values an admin manages together in Settings: usually the
 * credentials of one third-party app or API key, or the billing options. Each
 * field overrides one config key, so the code that reads `config('services.…')`
 * or `config('billing.…')` needs no change. The value is the
 * `admin_settings.key` of the group's row, so never rename a case value.
 */
enum SettingGroup: string
{
    case GoogleLogin = 'google-login';
    case MicrosoftLogin = 'microsoft-login';
    case GithubLogin = 'github-login';
    case SlackLogin = 'slack-login';

    case Messenger = 'messenger';
    case Instagram = 'instagram';
    case Whatsapp = 'whatsapp';

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

    case Billing = 'billing';
    case Stripe = 'stripe';

    case Resend = 'resend';
    case Turnstile = 'turnstile';
    case Ip2Location = 'ip2location';

    public function category(): SettingCategory
    {
        return match ($this) {
            self::GoogleLogin, self::MicrosoftLogin, self::GithubLogin, self::SlackLogin => SettingCategory::SignIn,
            self::Messenger, self::Instagram, self::Whatsapp => SettingCategory::Channels,
            self::Scaleway, self::Mistral, self::OpenRouter, self::Groq, self::Cohere, self::Jina => SettingCategory::Ai,
            self::Firecrawl, self::ChunkFit => SettingCategory::Knowledge,
            self::Billing, self::Stripe => SettingCategory::Billing,
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
            self::Messenger => 'Messenger',
            self::Instagram => 'Instagram',
            self::Whatsapp => 'WhatsApp',
            self::Scaleway => 'Scaleway AI',
            self::Mistral => 'Mistral',
            self::OpenRouter => 'OpenRouter',
            self::Groq => 'Groq',
            self::Cohere => 'Cohere',
            self::Jina => 'Jina',
            self::Firecrawl => 'Firecrawl',
            self::ChunkFit => 'chunk.fit',
            self::Billing => 'Billing',
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
            self::Messenger => 'Meta app that connects Facebook Pages to agents.',
            self::Instagram => 'Meta app that connects Instagram professional accounts to agents.',
            self::Whatsapp => 'Meta app for WhatsApp Embedded Signup and the Cloud API.',
            self::Scaleway => 'Chat, Qwen3 embeddings, reranking and speech to text.',
            self::Mistral => 'Chat, document OCR and whole-PDF reading.',
            self::OpenRouter => 'Chat models from many providers through one key.',
            self::Groq => 'Fast chat models.',
            self::Cohere => 'Embeddings and reranking.',
            self::Jina => 'Embeddings.',
            self::Firecrawl => 'Imports websites as knowledge sources.',
            self::ChunkFit => 'Hosted text splitter for documents.',
            self::Billing => 'Paid plans on or off, sign-up, free trial, currency and discount.',
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
            self::Messenger => asset('images/logos/channels/messenger.svg'),
            self::Instagram => asset('images/logos/channels/instagram.svg'),
            self::Whatsapp => asset('images/logos/channels/whatsapp.svg'),
            self::Mistral => asset('images/llms/mistral.svg'),
            self::Cohere => asset('images/llms/cohere.svg'),
            self::Stripe => asset('images/logos/connectors/stripe.svg'),
            self::Billing => Heroicon::OutlinedBanknotes,
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
            self::Messenger => $this->metaApp('messenger', redirectPath: '/my/channels/messenger/oauth/callback'),
            self::Instagram => $this->metaApp('instagram', redirectPath: '/my/channels/instagram/oauth/callback'),
            self::Whatsapp => $this->metaApp('whatsapp'),
            self::Scaleway => [SettingField::secret('api_key', 'API key', 'services.scalewayai.api_key')],
            self::Mistral => [SettingField::secret('api_key', 'API key', 'services.mistral.api_key')],
            self::OpenRouter => [SettingField::secret('api_key', 'API key', 'services.openrouter.api_key')],
            self::Groq => [SettingField::secret('api_key', 'API key', 'services.groq.api_key')],
            self::Cohere => [SettingField::secret('api_key', 'API key', 'services.cohere.api_key')],
            self::Jina => [SettingField::secret('api_key', 'API key', 'services.jina.api_key')],
            self::Firecrawl => [SettingField::secret('api_key', 'API key', 'services.firecrawl.key')],
            self::ChunkFit => [SettingField::secret('api_key', 'API key', 'services.chunkfit.api_key')],
            self::Billing => $this->billing(),
            self::Stripe => [
                SettingField::text('key', 'Publishable key', 'cashier.key', required: true, rules: ['starts_with:pk_']),
                SettingField::secret('secret', 'Secret key', 'cashier.secret', rules: ['starts_with:sk_,rk_'],
                    help: 'A secret key (sk_) or a restricted key (rk_).'),
                SettingField::secret('webhook_secret', 'Webhook signing secret', 'cashier.webhook.secret', rules: ['starts_with:whsec_'],
                    help: 'With billing on, production refuses web requests until it is set.'),
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
            self::Messenger, self::Instagram, self::Whatsapp => 'https://developers.facebook.com/apps/',
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
            self::Messenger => ['OAuth redirect' => 'channels.messenger.callback', 'Webhook callback URL' => 'messenger.webhook'],
            self::Instagram => ['OAuth redirect' => 'channels.instagram.callback', 'Webhook callback URL' => 'instagram.webhook'],
            self::Whatsapp => ['Webhook callback URL' => 'whatsapp.webhook'],
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

    public function hasSecrets(): bool
    {
        return array_any($this->fields(), fn (SettingField $field): bool => $field->isSecret());
    }

    /**
     * A caution shown above the form, for a group whose change has effects
     * outside this app.
     */
    public function warning(): ?string
    {
        return match ($this) {
            self::Billing => 'Turning paid plans off removes the plan limits and stops all charging inside the app. '
                .'It does not cancel subscriptions in Stripe. Stripe keeps charging them until you cancel them in the Stripe dashboard.',
            default => null,
        };
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
     * The Meta app of one channel. Without a redirect path it is WhatsApp:
     * Embedded Signup has no redirect, needs the Login configuration, and can
     * register numbers with a PIN.
     *
     * @return list<SettingField>
     */
    private function metaApp(string $channel, ?string $redirectPath = null): array
    {
        $config = "services.meta.{$channel}";

        $app = [
            SettingField::text('app_id', 'App ID', "{$config}.app_id", required: true),
            SettingField::secret('app_secret', 'App secret', "{$config}.app_secret"),
            SettingField::secret('webhook_verify_token', 'Webhook verify token', "{$config}.webhook_verify_token",
                help: 'Any random string. Paste the same value in the webhook settings of the Meta app.'),
        ];

        if ($redirectPath === null) {
            return [
                ...$app,
                SettingField::text('config_id', 'Login configuration ID', "{$config}.config_id", required: true,
                    help: 'The Facebook Login for Business configuration that Embedded Signup opens.'),
                SettingField::secret('register_pin', 'Number registration PIN', "{$config}.register_pin",
                    required: false, rules: ['digits:6', 'not_in:000000'],
                    help: 'Six digits used to register connected numbers for the Cloud API. Do not use 000000.'),
            ];
        }

        return [
            ...$app,
            SettingField::text('config_id', 'Login configuration ID', "{$config}.config_id",
                help: 'Facebook Login for Business configuration. Without it the classic permission request is used.'),
            SettingField::url('redirect', 'Redirect URL', "{$config}.redirect", $redirectPath,
                help: 'Leave empty to use the URL shown. It must be a valid OAuth redirect URI in the Meta app.'),
        ];
    }

    /**
     * Paid plans, sign-up, trial, currency and discount. A fresh install has
     * paid plans off, so it runs free without a Stripe account.
     *
     * @return list<SettingField>
     */
    private function billing(): array
    {
        return [
            SettingField::toggle('enabled', 'Paid plans', 'billing.enabled', default: false,
                help: 'On: workspaces choose a plan and pay with Stripe. Off: every workspace gets every feature, with no limits, and the billing pages are hidden.'),
            SettingField::toggle('registration_enabled', 'Open sign-up', 'billing.registration_enabled',
                default: fn (array $earlier): bool => ! $earlier['enabled'],
                help: 'Anyone can create an account at /register. With paid plans on, people normally sign up through the trial checkout, so keep this off.'),
            SettingField::integer('trial_days', 'Free trial days', 'billing.trial.days', default: 7, min: 0, max: 730,
                help: 'The free trial of a new subscription. 0 means no trial.'),
            SettingField::select('currency', 'Currency', 'billing.currency', options: [
                'eur' => 'Euro (EUR)',
                'usd' => 'US dollar (USD)',
                'gbp' => 'British pound (GBP)',
                'chf' => 'Swiss franc (CHF)',
                'sek' => 'Swedish krona (SEK)',
                'nok' => 'Norwegian krone (NOK)',
                'dkk' => 'Danish krone (DKK)',
                'pln' => 'Polish złoty (PLN)',
                'czk' => 'Czech koruna (CZK)',
                'huf' => 'Hungarian forint (HUF)',
                'ron' => 'Romanian leu (RON)',
            ], default: 'eur', help: 'Use the currency of your plan prices in Stripe.', alsoFills: [
                'cashier.currency' => fn (mixed $currency): mixed => $currency,
                'billing.currency_symbol' => fn (mixed $currency): string => self::currencySymbol((string) $currency),
            ]),
            SettingField::integer('unlimited_retention_days', 'Days to keep conversations', 'billing.unlimited_retention_days', default: 366, min: 1,
                help: 'Used while paid plans are off. With paid plans on, each plan has its own number of days.'),
            SettingField::integer('discount_percentage', 'Discount (%)', 'billing.discount.percentage', default: 0, min: 0, max: 100,
                help: 'Shown on the plans page. It does not change what Stripe charges: for that, add a coupon in Stripe. 0 means no discount.'),
            SettingField::date('discount_ends_at', 'Discount end date', 'billing.discount.ends_at',
                help: 'The discount is shown until this day starts. Leave empty for no end date.'),
            SettingField::integer('auto_recharge_max_per_day', 'Automatic top-ups per day', 'billing.addons.auto_recharge_credits.max_per_day', default: 3, min: 1,
                help: 'The most automatic credit top-ups one workspace can have in 24 hours. This limits the charges when a widget gets a lot of abusive traffic.'),
        ];
    }

    /**
     * The symbol shown before prices: € for eur, but SEK for sek.
     */
    private static function currencySymbol(string $currency): string
    {
        $locale = (string) config('cashier.currency_locale', 'en');
        $formatter = new NumberFormatter($locale.'@currency='.strtoupper($currency), NumberFormatter::CURRENCY);

        return $formatter->getSymbol(NumberFormatter::CURRENCY_SYMBOL) ?: strtoupper($currency);
    }

    /**
     * @return list<SettingField>
     */
    private function oauthLogin(string $service): array
    {
        return [
            SettingField::text('client_id', 'Client ID', "services.{$service}.client_id", required: true),
            SettingField::secret('client_secret', 'Client secret', "services.{$service}.client_secret"),
            SettingField::url('redirect', 'Redirect URL', "services.{$service}.redirect", "/auth/{$service}/callback",
                help: 'Leave empty to use the URL shown. It must match a redirect URL registered in the OAuth app.'),
        ];
    }
}
