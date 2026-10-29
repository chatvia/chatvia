<?php

declare(strict_types=1);

use App\Admin\Settings\ConnectorSettings;
use Domain\Integration\Connector\Catalog\Connector;
use Domain\Integration\Connector\Catalog\PreRegisteredOAuthConnector;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Illuminate\Support\Str;

/**
 * The connector classes listed in config/connectors.php form one consistent
 * catalog: each builds its data under a unique slug, and a connector whose
 * OAuth app the admin registers has a place for that app in Settings →
 * Connectors.
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

it('gives every pre-registered OAuth connector a client id and secret in the admin settings', function (string $class): void {
    $connector = app($class);
    $property = Str::snake(Str::replace('-', '_', $connector->slug));
    $keys = ConnectorSettings::configKeys();

    expect($keys)->toHaveKeys(["{$property}_client_id", "{$property}_client_secret"])
        ->and($keys["{$property}_client_id"][0]->key)->toBe($connector->oauthAppConfigKey('client_id'))
        ->and($keys["{$property}_client_secret"][0]->key)->toBe($connector->oauthAppConfigKey('client_secret'));
})->with(cctClasses(PreRegisteredOAuthConnector::class));
