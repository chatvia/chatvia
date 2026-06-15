<?php

use App\Http\Middleware\Web\ResolveLandingLocale;
use App\Identity\Workspace\Models\User;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;

/** Run the landing-locale middleware against a crafted request. */
function runLandingLocale(Request $request): mixed
{
    return app(ResolveLandingLocale::class)->handle($request, fn (): ResponseFactory|\Illuminate\Http\Response => response('ok'));
}

it('persists a landing locale choice to the signed-in user (landing → dashboard)', function (): void {
    $user = User::factory()->create(['locale' => 'en']);
    $request = Request::create('/de', 'GET');
    $request->setUserResolver(fn () => $user);

    runLandingLocale($request);

    expect($user->fresh()->locale)->toBe('de');
    expect(session('locale'))->toBe('de');
});

it('seeds the session from a supported saved locale on an un-prefixed URL (dashboard → landing)', function (): void {
    $user = User::factory()->create(['locale' => 'fr']);
    $request = Request::create('/', 'GET');
    $request->setUserResolver(fn () => $user);

    runLandingLocale($request);

    expect(session('locale'))->toBe('fr');      // localeSessionRedirect will route them to /fr
    expect($user->fresh()->locale)->toBe('fr'); // preference untouched
});

it('does not seed the session from a saved locale that is not offered on the landing', function (): void {
    // The dashboard SPA supports more locales than the public site (e.g. Japanese is
    // a dashboard UI language but not one of the public-site landing locales). Such a
    // preference must fall through to the English default, not seed a session mcamara
    // can't route.
    $user = User::factory()->create(['locale' => 'ja']);
    $request = Request::create('/', 'GET');
    $request->setUserResolver(fn () => $user);

    runLandingLocale($request);

    expect(session('locale'))->toBeNull();
    expect($user->fresh()->locale)->toBe('ja'); // dashboard preference untouched
});

it('does not change the preference on a non-locale path', function (): void {
    $user = User::factory()->create(['locale' => 'en']);
    $request = Request::create('/pricing', 'GET');
    $request->setUserResolver(fn () => $user);

    runLandingLocale($request);

    expect($user->fresh()->locale)->toBe('en');
});

it('does not auto-redirect a guest by browser language (no auto-detect — they pick from the switcher)', function (): void {
    $request = Request::create('/', 'GET', server: ['HTTP_ACCEPT_LANGUAGE' => 'ar,en;q=0.5']);

    $response = runLandingLocale($request);

    expect($response->isRedirect())->toBeFalse();
    expect($response->getContent())->toBe('ok'); // passed through to the English default
});

it('leaves a guest on the English default', function (): void {
    $request = Request::create('/', 'GET', server: ['HTTP_ACCEPT_LANGUAGE' => 'de-DE,de']);

    $response = runLandingLocale($request);

    expect($response->getContent())->toBe('ok'); // passed through, no redirect
});
