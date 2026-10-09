<?php

use App\Admin\Settings\AdminSetting;
use App\Admin\Settings\SettingGroup;
use App\Admin\Settings\SettingsStore;
use App\Admin\Settings\SettingStatus;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;

/**
 * The credentials saved in the admin panel are stored encrypted and override
 * the .env values in config, so the code that reads config needs no change.
 */

/**
 * A store that has not applied anything yet, so it takes the config set by the
 * test as the .env values.
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

it('puts saved values in config over the .env values', function (): void {
    config(['services.google.client_id' => 'env-id', 'services.google.client_secret' => 'env-secret']);
    $store = freshSettingsStore();

    saveAdminSettings(SettingGroup::GoogleLogin, ['client_id' => 'saved-id']);
    $store->apply();

    expect(config('services.google.client_id'))->toBe('saved-id')
        ->and(config('services.google.client_secret'))->toBe('env-secret');
});

it('stores the payload encrypted', function (): void {
    saveAdminSettings(SettingGroup::Stripe, ['secret' => 'sk_test_plaintext']);

    $raw = DB::table('admin_settings')->where('key', 'stripe')->value('payload');

    expect($raw)->not->toContain('sk_test_plaintext')
        ->and(json_decode(Crypt::decryptString($raw), true))->toBe(['secret' => 'sk_test_plaintext']);
});

it('goes back to the .env value when a saved value is removed', function (): void {
    config(['services.mistral.api_key' => 'env-key']);
    $store = freshSettingsStore();

    $setting = saveAdminSettings(SettingGroup::Mistral, ['api_key' => 'saved-key']);
    $store->apply();
    expect(config('services.mistral.api_key'))->toBe('saved-key');

    $setting->forgetValue(SettingGroup::Mistral->fields()[0], null);
    $store->apply();

    expect(config('services.mistral.api_key'))->toBe('env-key')
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

it('keeps the .env value when a payload cannot be decrypted', function (): void {
    Exceptions::fake();
    config(['services.cohere.api_key' => 'env-key']);
    DB::table('admin_settings')->insert(['key' => 'cohere', 'payload' => 'not-ciphertext', 'created_at' => now(), 'updated_at' => now()]);

    $store = freshSettingsStore();
    $store->forget();
    $store->apply();

    expect(config('services.cohere.api_key'))->toBe('env-key');
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

it('reports where a group gets its values from', function (array $env, ?array $saved, SettingStatus $status): void {
    config(['services.google.client_id' => $env['id'] ?? null, 'services.google.client_secret' => $env['secret'] ?? null]);
    $store = freshSettingsStore();

    $setting = $saved === null ? null : saveAdminSettings(SettingGroup::GoogleLogin, $saved);

    expect($store->status(SettingGroup::GoogleLogin, $setting))->toBe($status);
})->with([
    'nothing set' => [[], null, SettingStatus::Missing],
    'all in .env' => [['id' => 'a', 'secret' => 'b'], null, SettingStatus::FromEnv],
    'saved over .env' => [['id' => 'a'], ['client_secret' => 'b'], SettingStatus::Configured],
    'secret missing' => [[], ['client_id' => 'a'], SettingStatus::Incomplete],
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
            expect(config()->has($field->config))->toBeTrue("{$group->value}.{$field->name} → {$field->config}");
        }
    }
});
