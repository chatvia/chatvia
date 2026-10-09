<?php

namespace App\Http\Controllers\Settings\Account;

use App\Identity\Workspace\Enums\SocialProvider;
use App\Identity\Workspace\Models\UserSocial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Linked social sign-in accounts: list, connect and disconnect.
 */
class SocialController
{
    /**
     * Providers we have OAuth callbacks configured for. LinkedIn / X exist in
     * the enum but aren't wired up to a Socialite driver yet, so we hide them
     * from the settings page rather than offering a broken Connect button.
     */
    private const SUPPORTED = [
        SocialProvider::Google,
        SocialProvider::Microsoft,
        SocialProvider::Slack,
        SocialProvider::Github,
    ];

    public function edit(Request $request): Response
    {
        $linked = $request->user()
            ->socials()
            ->get(['provider', 'created_at'])
            ->keyBy(fn (UserSocial $s) => $s->provider->value);

        return Inertia::render('account/Social', [
            'accounts' => collect(self::SUPPORTED)->map(fn (SocialProvider $p): array => [
                'provider' => $p->value,
                'label' => ucfirst($p->value),
                'connected' => $linked->has($p->value),
                'connected_at' => $linked->get($p->value)?->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * Kick off the OAuth round-trip to *link* the provider to the current
     * user. We piggy-back on the same `/auth/{provider}/callback` URL the
     * sign-in flow uses (the only URL registered with the OAuth provider)
     * and drop a session marker so the shared SocialOauthCallback knows to
     * treat the return trip as a link rather than a fresh sign-in.
     */
    public function connect(string $provider): RedirectResponse
    {
        $this->resolveProvider($provider);

        session(['social.intent' => 'link']);

        return redirect()->route("auth.{$provider}");
    }

    public function disconnect(string $provider, Request $request): RedirectResponse
    {
        $providerEnum = $this->resolveProvider($provider);

        $request->user()->socials()->where('provider', $providerEnum)->delete();

        return to_route('account.social.edit')
            ->with('success', __(':provider disconnected.', ['provider' => ucfirst($providerEnum->value)]));
    }

    private function resolveProvider(string $provider): SocialProvider
    {
        $enum = SocialProvider::tryFrom($provider);

        if ($enum === null || ! in_array($enum, self::SUPPORTED, strict: true)) {
            abort(404);
        }

        return $enum;
    }
}
