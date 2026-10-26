<?php

declare(strict_types=1);

use Domain\Integration\Connector\Catalog\Connectors;

/**
 * The connector catalog: every connector a workspace can connect, one class
 * each under `Domain\Integration\Connector\Catalog\Connectors`. To add one,
 * extend the base that matches how it connects (see
 * `Domain\Integration\Connector\Catalog\Connector`), add its logo to
 * `public/images/logos/connectors/{slug}.svg`, its text to the
 * `connectors_catalog` i18n fragments, and list it here. A
 * `PreRegisteredOAuthConnector` also needs its client id and secret in
 * `App\Admin\Settings\ConnectorSettings`.
 *
 * `oauth_apps` holds the OAuth apps an admin registers for those connectors,
 * filled from Settings → Connectors: `oauth_apps.{slug}.client_id|client_secret`.
 */
return [

    'catalog' => [
        Connectors\PerplexityConnector::class,
        Connectors\WeatherConnector::class,
        Connectors\CurrencyConnector::class,
        Connectors\ChartsConnector::class,
        Connectors\FormsConnector::class,
        Connectors\CalendarConnector::class,
        Connectors\StripeConnector::class,
        Connectors\GithubConnector::class,
        Connectors\NotionConnector::class,
        Connectors\LinearConnector::class,
        Connectors\AtlassianConnector::class,
        Connectors\GmailConnector::class,
        Connectors\GoogleCalendarConnector::class,
        Connectors\GoogleDriveConnector::class,
        Connectors\SlackConnector::class,
        Connectors\CalendlyConnector::class,
        Connectors\TodoistConnector::class,
        Connectors\ZapierConnector::class,
        Connectors\ZendeskConnector::class,
        Connectors\HubspotConnector::class,
        Connectors\SalesforceConnector::class,
        Connectors\BoxConnector::class,
        Connectors\AsanaConnector::class,
        Connectors\SentryConnector::class,
        Connectors\FirefliesConnector::class,
        Connectors\AmplitudeConnector::class,
        Connectors\SerpapiConnector::class,
        Connectors\IntercomConnector::class,
        Connectors\GorgiasConnector::class,
        Connectors\HelpScoutConnector::class,
        Connectors\FrontConnector::class,
        Connectors\PipedriveConnector::class,
        Connectors\AttioConnector::class,
        Connectors\KlaviyoConnector::class,
        Connectors\MondayConnector::class,
        Connectors\AirtableConnector::class,
        Connectors\PaypalConnector::class,
    ],

    'oauth_apps' => [],

];
