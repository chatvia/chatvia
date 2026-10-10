<?php

namespace App\Http\Controllers\Settings\Account;

use App\Identity\Workspace\Enums\SocialProvider;
use Illuminate\Http\RedirectResponse;

/**
 * Kicks off the OAuth round-trip to *link* a social provider to the signed-in
 * user. It piggy-backs on the same `/auth/{provider}/callback` URL the sign-in
 * flow uses (the only URL registered with the OAuth provider) and drops a
 * session marker so the shared SocialOauthCallback knows to treat the return
 * trip as a link rather than a fresh sign-in.
 */
class ConnectSocialAccountController
{
    public function __invoke(string $provider): RedirectResponse
    {
        SocialProvider::tryLinkable($provider) ?? abort(404);

        session(['social.intent' => 'link']);

        return redirect()->route("auth.{$provider}");
    }
}
