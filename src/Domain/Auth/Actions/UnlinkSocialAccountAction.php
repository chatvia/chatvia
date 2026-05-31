<?php

namespace Domain\Auth\Actions;

use App\Auth\Models\User;
use Support\Enums\SocialProvider;

class UnlinkSocialAccountAction
{
    public function execute(User $user, SocialProvider $provider): void
    {
        $user->socials()
            ->where('provider', $provider)
            ->delete();
    }
}
