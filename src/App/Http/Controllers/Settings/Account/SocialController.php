<?php

namespace App\Http\Controllers\Settings\Account;

use App\Identity\Workspace\Data\Account\SocialAccountRowData;
use App\Identity\Workspace\Enums\SocialProvider;
use App\Identity\Workspace\Models\UserSocial;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Linked social sign-in accounts: list and disconnect (linking one is
 * {@see ConnectSocialAccountController}).
 */
class SocialController
{
    public function edit(Request $request): Response
    {
        // No sign-in provider set up (Settings → Sign-in): nothing can be
        // connected, and the account menu hides the page too.
        abort_if(SocialProvider::configured() === [], 404);

        $linked = $request->user()
            ->socials()
            ->get(['provider', 'created_at'])
            ->keyBy(fn (UserSocial $s) => $s->provider->value);

        return Inertia::render('account/Social', [
            // A provider that is not set up cannot be connected; one already
            // linked still shows, so it can be disconnected.
            'accounts' => collect(SocialProvider::linkable())
                ->filter(fn (SocialProvider $p): bool => $p->isConfigured() || $linked->has($p->value))
                ->map(fn (SocialProvider $p): SocialAccountRowData => SocialAccountRowData::fromProvider($p, $linked->get($p->value)))
                ->values(),
        ]);
    }

    public function destroy(string $provider, Request $request): RedirectResponse
    {
        $providerEnum = SocialProvider::tryLinkable($provider) ?? abort(404);

        $request->user()->socials()->where('provider', $providerEnum)->delete();

        return to_route('account.social.edit')
            ->with('success', __(':provider disconnected.', ['provider' => ucfirst($providerEnum->value)]));
    }
}
