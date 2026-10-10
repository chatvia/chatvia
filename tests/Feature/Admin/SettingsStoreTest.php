<?php

use App\Admin\Settings\AdminSetting;
use App\Admin\Settings\SettingGroup;
use App\Admin\Settings\SettingsStore;
use App\Admin\Settings\SettingStatus;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;

/**
 * The credentials saved in the admin panel are stored encrypted and put into
 * config, so the code that reads config needs no change. The admin panel is
 * the only source: .env is not read for these keys.
 */
function freshSettingsStore(): SettingsStore
{
    app()->forgetInstance(SettingsStore::class);

    return app(SettingsStore::class);
}

function saveAdminSettings(SettingGroup $group, ?array $payload): AdminSetting
{
    $setting = AdminSetting::forGroup($group);
    $setting->update(['payload' => $payload]);

    return $setting;
}

it('puts saved values in config and empties the rest', function (): void {
    config(['services.google.client_id' => 'old-id', 'services.google.client_secret' => 'old-secret']);
    $store = freshSettingsStore();

    saveAdminSettings(SettingGroup::GoogleLogin, ['client_id' => 'saved-id']);
    $store->apply();

    expect(config('services.google.client_id'))->toBe('saved-id')
        ->and(config('services.google.client_secret'))->toBeNull();
});

it('uses the callback URL of this app for a redirect with nothing saved', function (): void {
    freshSettingsStore()->apply();

    expect(config('services.google.redirect'))->toBe(url('/auth/google/callback'))
        ->and(config('services.meta.instagram.redirect'))->toBe(url('/my/channels/instagram/oauth/callback'));
});

it('stores the payload encrypted', function (): void {
    saveAdminSettings(SettingGroup::Stripe, ['secret' => 'sk_test_plaintext']);

    $raw = DB::table('admin_settings')->where('key', 'stripe')->value('payload');

    expect($raw)->not->toContain('sk_test_plaintext')
        ->and(json_decode(Crypt::decryptString($raw), true))->toBe(['secret' => 'sk_test_plaintext']);
});

it('empties a value removed in the panel in a running process', function (): void {
    $store = freshSettingsStore();

    $setting = saveAdminSettings(SettingGroup::Mistral, ['api_key' => 'saved-key']);
    $store->apply();
    expect(config('services.mistral.api_key'))->toBe('saved-key');

    $setting->forgetValue(SettingGroup::Mistral->fields()[0], null);
    $store->apply();

    expect(config('services.mistral.api_key'))->toBeNull()
        ->and($setting->fresh()->payload)->toBeNull();
});

it('applies changes before a queued job runs', function (): void {
    saveAdminSettings(SettingGroup::Firecrawl, ['api_key' => 'fc-saved']);

    // The closure is serialized onto the (sync) queue, so it reports back
    // through the cache.
    dispatch(function (): void {
        cache()->put('firecrawl-key-seen-by-job', config('services.firecrawl.key'));
    });

    expect(cache('firecrawl-key-seen-by-job'))->toBe('fc-saved');
});

it('skips a payload it cannot decrypt instead of failing', function (): void {
    Exceptions::fake();
    DB::table('admin_settings')->insert(['key' => 'cohere', 'payload' => 'not-ciphertext', 'created_at' => now(), 'updated_at' => now()]);

    $store = freshSettingsStore();
    $store->forget();
    $store->apply();

    expect(config('services.cohere.api_key'))->toBeNull();
    Exceptions::assertReported(DecryptException::class);
});

it('skips rows of groups that no longer exist', function (): void {
    DB::table('admin_settings')->insert([
        'key' => 'retired-group',
        'payload' => Crypt::encryptString(json_encode(['api_key' => 'x'])),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $store = freshSettingsStore();
    $store->forget();
    $store->apply();
})->throwsNoExceptions();

it('reports whether a group has the values it needs', function (?array $saved, SettingStatus $status): void {
    $setting = $saved === null ? null : saveAdminSettings(SettingGroup::GoogleLogin, $saved);

    expect(freshSettingsStore()->status(SettingGroup::GoogleLogin, $setting))->toBe($status);
})->with([
    'nothing saved' => [null, SettingStatus::Missing],
    'empty row' => [[], SettingStatus::Missing],
    'secret missing' => [['client_id' => 'a'], SettingStatus::Incomplete],
    'only the optional redirect' => [['redirect' => 'https://example.com/cb'], SettingStatus::Incomplete],
    'every required value' => [['client_id' => 'a', 'client_secret' => 'b'], SettingStatus::Configured],
]);

it('keeps a blank secret and drops other blank fields when saving an edit', function (): void {
    $setting = saveAdminSettings(SettingGroup::GoogleLogin, [
        'client_id' => 'old-id',
        'client_secret' => 'old-secret',
        'redirect' => 'https://example.com/old',
    ]);

    $payload = $setting->payloadFrom(['client_id' => ' new-id ', 'client_secret' => '', 'redirect' => '']);

    expect($payload)->toBe(['client_id' => 'new-id', 'client_secret' => 'old-secret']);
});

it('maps every field to a config key that exists', function (): void {
    foreach (SettingGroup::cases() as $group) {
        foreach ($group->fields() as $field) {
            foreach ([$field->config, ...array_keys($field->alsoFills)] as $config) {
                expect(config()->has($config))->toBeTrue("{$group->value}.{$field->name} → {$config}");
            }
        }
    }
});

it('reports a group without required fields as set up once something is saved', function (): void {
    $store = freshSettingsStore();

    expect($store->status(SettingGroup::Billing, null))->toBe(SettingStatus::Missing)
        ->and($store->status(SettingGroup::Billing, saveAdminSettings(SettingGroup::Billing, ['enabled' => '0'])))->toBe(SettingStatus::Configured);
});

it('reads the table directly when the cache is unreachable', function (): void {
    Exceptions::fake();
    saveAdminSettings(SettingGroup::GoogleLogin, ['client_id' => 'saved-id']);

    $cache = Mockery::mock(Repository::class);
    $cache->shouldReceive('rememberForever')->andThrow(new RuntimeException('cache down'));

    new SettingsStore(app('config'), $cache, app('db'))->apply();

    expect(config('services.google.client_id'))->toBe('saved-id');
    Exceptions::assertReported(RuntimeException::class);
});
