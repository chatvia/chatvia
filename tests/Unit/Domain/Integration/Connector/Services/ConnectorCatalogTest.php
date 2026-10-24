<?php

declare(strict_types=1);

use App\Admin\Settings\ConnectorSettings;
use Domain\Integration\Connector\Catalog\Connector;
use Domain\Integration\Connector\Catalog\PreRegisteredOAuthConnector;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * The connector classes listed in config/connectors.php form one consistent
 * catalog: each builds its data, has a unique slug, a logo and English text,
 * and a connector whose OAuth app the admin registers has a place for that
 * app in Settings → Connectors.
 */
/**
 * The listed connector classes, keyed by class name. Read from the config file
 * itself: a dataset is built before the app boots.
 *
 * @param  class-string<Connector>  $base
 * @return array<string, array{class-string<Connector>}>
 */
function cctClasses(string $base = Connector::class): array
{
    $classes = (require dirname(__DIR__, 6).'/config/connectors.php')['catalog'];

    return collect($classes)
        ->filter(fn (string $class): bool => is_a($class, $base, true))
        ->mapWithKeys(fn (string $class): array => [class_basename($class) => [$class]])
        ->all();
}

it('builds the data of every listed connector, keyed by a unique slug', function (): void {
    $classes = config('connectors.catalog');
    $catalog = app(ConnectorCatalog::class);

    expect($catalog->definitions())->toHaveCount(count($classes))
        ->and($catalog->all()->keys()->all())->toEqualCanonicalizing($catalog->definitions()->keys()->all())
        ->and($catalog->all())->each->toBeInstanceOf(ConnectorData::class);
});

it('has a logo file for every connector', function (string $class): void {
    $connector = app($class);

    expect(File::exists(public_path(Str::ltrim($connector->logoUrl, '/'))))->toBeTrue("No logo at {$connector->logoUrl}");
})->with(cctClasses());

it('has English catalog text for every connector and category', function (string $class): void {
    $connector = app($class);
    $text = File::json(resource_path('js/i18n/locales/_fragments/connectors_catalog.en.json'))['connectors'];

    expect($text['catalog'][$connector->slug]['name'] ?? null)->toBe($connector->name)
        ->and($text['catalog'][$connector->slug]['description'] ?? null)->toBe($connector->description)
        ->and($text['categories'])->toHaveKey(Str::snake(Str::lower($connector->category->value)));
})->with(cctClasses());

it('gives every pre-registered OAuth connector a client id and secret in the admin settings', function (string $class): void {
    $connector = app($class);
    $property = Str::snake(Str::replace('-', '_', $connector->slug));
    $keys = ConnectorSettings::configKeys();

    expect($keys)->toHaveKeys(["{$property}_client_id", "{$property}_client_secret"])
        ->and($keys["{$property}_client_id"][0]->key)->toBe($connector->oauthAppConfigKey('client_id'))
        ->and($keys["{$property}_client_secret"][0]->key)->toBe($connector->oauthAppConfigKey('client_secret'));
})->with(cctClasses(PreRegisteredOAuthConnector::class));

it('reads a pre-registered connector\'s OAuth app from config on every call', function (): void {
    $catalog = app(ConnectorCatalog::class);

    config(['connectors.oauth_apps.github.client_id' => 'first']);
    expect($catalog->find('github')?->oauthClientId)->toBe('first');

    config(['connectors.oauth_apps.github.client_id' => 'rotated']);
    expect($catalog->find('github')?->oauthClientId)->toBe('rotated')
        ->and($catalog->find('github')?->hasPreRegisteredOAuth())->toBeTrue();
});

it('points the connectors this app hosts at its own MCP routes', function (): void {
    config(['app.url' => 'https://chat.example.test/']);

    expect(app(ConnectorCatalog::class)->find('perplexity')?->mcpUrl)->toBe('https://chat.example.test/mcp/perplexity')
        ->and(app(ConnectorCatalog::class)->find('charts')?->isCapability)->toBeTrue();
});
