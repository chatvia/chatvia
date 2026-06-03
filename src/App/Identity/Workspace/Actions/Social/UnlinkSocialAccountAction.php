<?php

namespace App\Identity\Workspace\Actions\Social;

use App\Identity\Workspace\Models\User;
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
