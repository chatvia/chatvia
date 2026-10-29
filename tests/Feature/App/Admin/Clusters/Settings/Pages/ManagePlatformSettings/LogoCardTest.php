<?php

use App\Admin\Clusters\Settings\Pages\ManagePlatformSettings;
use App\Admin\Settings\CustomLogo;
use App\Admin\Settings\PlatformSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

/**
 * Settings → Platform → Logo: a licensed install's own icon replaces the
 * ChatVia.ai mark in the admin panel and on the sign-in pages, and its
 * favicons replace the ChatVia.ai ones.
 */
beforeEach(function (): void {
    Storage::fake('assets');
    $this->admin = adminUser();
});

it('shows a licensed install\'s icon and favicons, and deletes them once removed', function (): void {
    withLicense();

    adminPage(ManagePlatformSettings::class, $this->admin)
        ->fillForm(['logo' => UploadedFile::fake()->image('logo.png', 600, 600)])
        ->call('save')
        ->assertHasNoFormErrors();

    $path = app(PlatformSettings::class)->logo;
    $favicons = CustomLogo::faviconDirectory($path);
    $disk = Storage::disk('assets');

    $disk->assertExists([$path, ...collect(CustomLogo::FAVICONS)->keys()->map(fn (string $file): string => "{$favicons}/{$file}")]);
    expect(getimagesizefromstring($disk->get("{$favicons}/apple-touch-icon.png"))[0])->toBe(180)
        ->and(substr($disk->get("{$favicons}/favicon.ico"), 0, 6))->toBe(pack('vvv', 0, 1, 2));

    $this->get(route('login'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('logo', $disk->url($path)))
        ->assertSee('href="'.$disk->url("{$favicons}/favicon.ico").'"', false)
        ->assertDontSee('favicon.svg');
    $this->get(route('web-manifest'))
        ->assertJsonPath('icons.0.src', $disk->url("{$favicons}/web-app-manifest-192x192.png"));
    $this->actingAs($this->admin, 'admin')->get(ManagePlatformSettings::getUrl())
        ->assertSee('src="'.$disk->url($path).'"', false);

    adminPage(ManagePlatformSettings::class, $this->admin)
        ->fillForm(['logo' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    $disk->assertMissing([$path, "{$favicons}/favicon.ico"]);
    $this->get(route('login'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('logo', null))
        ->assertSee('href="'.asset('images/favicons/favicon.svg').'"', false);
});

it('turns down an icon too small for the favicons', function (): void {
    withLicense();

    adminPage(ManagePlatformSettings::class, $this->admin)
        ->fillForm(['logo' => UploadedFile::fake()->image('logo.png', 256, 256)])
        ->call('save')
        ->assertHasFormErrors(['logo']);

    expect(app(PlatformSettings::class)->logo)->toBeNull();
});

it('shows the ChatVia.ai mark and favicons without a license key, and keeps the saved icon', function (): void {
    withLicense();

    adminPage(ManagePlatformSettings::class, $this->admin)
        ->fillForm(['logo' => UploadedFile::fake()->image('logo.png', 512, 512)])
        ->call('save');

    withoutLicense();

    adminPage(ManagePlatformSettings::class, $this->admin)
        ->assertFormFieldDisabled('logo');
    $this->get(route('login'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('logo', null))
        ->assertSee('href="'.asset('images/favicons/favicon.ico').'"', false);
    $this->get(route('web-manifest'))
        ->assertJsonPath('icons.0.src', asset('images/favicons/web-app-manifest-192x192.png'));
    expect(app(PlatformSettings::class)->logo)->not->toBeNull();
});
