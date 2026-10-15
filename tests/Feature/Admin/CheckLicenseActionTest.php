<?php

use App\Admin\Settings\Actions\CheckLicenseAction;
use App\Admin\Settings\BrandSettings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\LicenseKeys;

/**
 * The daily license check ({@see CheckLicenseAction}, `app:check-license`):
 * a key that chatvia.ai no longer has active for this domain is removed, and
 * an outage at chatvia.ai changes nothing.
 */
beforeEach(function (): void {
    LicenseKeys::trust();
    config([
        'app.url' => 'https://chat.acme.com',
        'chatvia.license.api_url' => 'https://licenses.test/api/licenses',
    ]);

    $settings = app(BrandSettings::class);
    $settings->powered_by_enabled = false;
    $settings->license_key = LicenseKeys::activatedFor('chat.acme.com');
    $settings->save();
});

function checkedLicenseKey(): ?string
{
    app()->forgetScopedInstances();

    return app(BrandSettings::class)->license_key;
}

it('removes a key that was deactivated at chatvia.ai', function (): void {
    Http::fake(['licenses.test/api/licenses/check' => Http::response(['active' => false])]);

    $this->artisan('app:check-license')->assertSuccessful();

    app()->forgetScopedInstances();

    expect(checkedLicenseKey())->toBeNull()
        ->and(app(BrandSettings::class)->powered_by_enabled)->toBeNull()
        ->and(config('chatvia.brand.powered_by.enabled'))->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request['id'] === LicenseKeys::ID
        && $request['domain'] === 'chat.acme.com'
        && ! isset($request['key']));
});

it('keeps a key that is still active', function (): void {
    Http::fake(['licenses.test/api/licenses/check' => Http::response(['active' => true])]);

    expect(app(CheckLicenseAction::class)->execute())->toBeFalse()
        ->and(checkedLicenseKey())->toBe(LicenseKeys::activatedFor('chat.acme.com'))
        ->and(config('chatvia.brand.powered_by.enabled'))->toBeFalse();
});

it('keeps the key when chatvia.ai gives no answer', function (Closure $answer): void {
    Http::fake($answer);

    expect(app(CheckLicenseAction::class)->execute())->toBeFalse()
        ->and(checkedLicenseKey())->toBe(LicenseKeys::activatedFor('chat.acme.com'));
})->with([
    'unreachable' => fn (): Closure => fn () => throw new ConnectionException('Connection refused'),
    'an error' => fn (): Closure => fn () => Http::response('Bad gateway', 502),
]);

it('asks nothing without a key that works here', function (): void {
    Http::fake();

    $settings = app(BrandSettings::class);
    $settings->license_key = null;
    $settings->save();

    expect(app(CheckLicenseAction::class)->execute())->toBeFalse();

    Http::assertNothingSent();
});
