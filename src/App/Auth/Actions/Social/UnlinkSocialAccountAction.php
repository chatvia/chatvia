<?php

namespace App\Auth\Actions\Social;

use App\Auth\Models\User;
use Support\Enums\User\SocialProvider;

class UnlinkSocialAccountAction
{
    public function execute(User $user, SocialProvider $provider): void
    {
        $user->socials()
            ->where('provider', $provider)
            ->delete();
    }
}
