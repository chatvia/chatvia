<?php

/**
 * A fresh install must work without the operator's cloud accounts, domains
 * or machine paths, while an install that sets the env keeps today's setup.
 * The config files are re-evaluated with a given environment (null unsets a
 * key).
 *
 * @param  array<string, string|null>  $env
 * @return array<string, mixed>
 */
function configFileWith(string $file, array $env): array
{
    $saved = [];

    foreach ($env as $key => $value) {
        $saved[$key] = [$_SERVER[$key] ?? null, $_ENV[$key] ?? null, getenv($key)];

        if ($value === null) {
            unset($_SERVER[$key], $_ENV[$key]);
            putenv($key);
        } else {
            $_SERVER[$key] = $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }

    try {
        return require config_path($file);
    } finally {
        foreach ($saved as $key => [$server, $envValue, $process]) {
            if ($server === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $server;
            }

            if ($envValue === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $envValue;
            }

            $process === false ? putenv($key) : putenv("{$key}={$process}");
        }
    }
}

/** @return array<string, string|null> */
function noCloudStorageEnv(): array
{
    return [
        'BUNNYCDN_STORAGE_ZONE' => null,
        'FILESYSTEM_ASSETS_DRIVER' => null,
        'APP_URL' => 'https://files.example.org',
    ];
}

it('stores files on the local disk when no bucket or CDN zone is configured', function (): void {
    $config = configFileWith('filesystems.php', noCloudStorageEnv());
    $disks = $config['disks'];

    expect($config['storage'])->toBe('local')
        ->and($disks['private'])->toMatchArray([
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'url' => 'https://files.example.org/private-storage',
        ])
        ->and($disks['s3']['bucket'])->toBeNull()
        ->and($disks)->not->toHaveKey('public')
        ->and($disks['assets'])->toMatchArray([
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => 'https://files.example.org/storage',
        ])
        // `php artisan storage:link` publishes the public folder at /storage.
        ->and($config['links'])->toBe([public_path('storage') => storage_path('app/public')]);
});

it('keeps the CDN when its env is set', function (): void {
    $assets = configFileWith('filesystems.php', [...noCloudStorageEnv(), 'BUNNYCDN_STORAGE_ZONE' => 'acme-zone'])['disks']['assets'];

    expect($assets)->toMatchArray(['driver' => 'bunnycdn', 'storage_zone' => 'acme-zone']);
});

it('lets an explicit driver override the detected one', function (): void {
    $assets = configFileWith('filesystems.php', [
        ...noCloudStorageEnv(),
        'BUNNYCDN_STORAGE_ZONE' => 'acme-zone',
        'FILESYSTEM_ASSETS_DRIVER' => 'local',
    ])['disks']['assets'];

    expect($assets['driver'])->toBe('local');
});

it('derives passkey origins from APP_URL plus the www variant', function (): void {
    config(['app.url' => 'https://acme.example']);

    expect(configFileWith('fortify.php', ['PASSKEY_EXTRA_ORIGINS' => null])['passkeys']['allowed_origins'])
        ->toBe(['https://acme.example', 'https://www.acme.example']);
});

it('adds extra passkey origins and skips the www variant for local hosts', function (): void {
    config(['app.url' => 'http://localhost:8000/']);

    expect(configFileWith('fortify.php', ['PASSKEY_EXTRA_ORIGINS' => ' https://app.acme.example/ , ,https://admin.acme.example'])['passkeys']['allowed_origins'])
        ->toBe(['http://localhost:8000', 'https://app.acme.example', 'https://admin.acme.example']);
});

it('resolves helper binaries from PATH by default, never from .env', function (): void {
    $bin = configFileWith('services.php', ['PDFINFO_BIN_PATH' => '/bin/sh'])['bin'];

    expect($bin)->toBe([
        'catdoc' => 'catdoc',
        'catppt' => 'catppt',
        'pandoc' => 'pandoc',
        'pdfinfo' => 'pdfinfo',
        'pdftotext' => 'pdftotext',
        'ffmpeg' => 'ffmpeg',
        'ffprobe' => 'ffprobe',
    ])->and(configFileWith('laravel-pdf.php', ['WEASYPRINT_BIN_PATH' => '/bin/sh'])['weasyprint']['binary'])->toBe('weasyprint');
});

it('reads no PDF service keys from .env: the admin panel sets them', function (): void {
    $pdf = configFileWith('laravel-pdf.php', [
        'LARAVEL_PDF_DRIVER' => 'gotenberg',
        'CLOUDFLARE_API_TOKEN' => 'token-from-env',
        'CLOUDFLARE_ACCOUNT_ID' => 'account-from-env',
        'GOTENBERG_URL' => 'http://gotenberg:3000',
    ]);

    expect($pdf['driver'])->toBe('weasyprint')
        ->and($pdf['cloudflare'])->toBe(['api_token' => null, 'account_id' => null])
        ->and($pdf['gotenberg'])->toBe(['url' => null, 'username' => null, 'password' => null]);
});

it('ships no Reverb credentials of its own: the one app reads them from env', function (): void {
    $set = configFileWith('reverb.php', [
        'REVERB_APP_KEY' => 'key-from-env',
        'REVERB_APP_SECRET' => 'secret-from-env',
        'REVERB_APP_ID' => 'id-from-env',
        'REVERB_EXTRA_APP_ID' => null,
    ])['apps']['apps'];
    $unset = configFileWith('reverb.php', [
        'REVERB_APP_KEY' => null,
        'REVERB_APP_SECRET' => null,
        'REVERB_APP_ID' => null,
        'REVERB_EXTRA_APP_ID' => null,
    ])['apps']['apps'];

    expect($set)->toHaveCount(1)
        ->and([$set[0]['key'], $set[0]['secret'], $set[0]['app_id']])->toBe(['key-from-env', 'secret-from-env', 'id-from-env'])
        ->and($unset)->toHaveCount(1)
        ->and([$unset[0]['key'], $unset[0]['secret'], $unset[0]['app_id']])->toBe([null, null, null]);
});

it('sends Horizon alerts nowhere unless an inbox is configured', function (): void {
    expect(configFileWith('horizon.php', ['HORIZON_NOTIFY_EMAIL' => null])['notify_email'])->toBeNull();
});
