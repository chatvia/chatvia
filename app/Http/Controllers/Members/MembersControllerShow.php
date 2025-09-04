<?php

namespace App\Http\Controllers\Members;

use App\Models\User;
use App\Models\UserQuota;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MembersControllerShow
{
    public function __invoke(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        /** @var UserQuota $quota */
        $quota = $user->quota;

        return Inertia::render('members/Index', [
            'team_collaboration' => $user->capabilityAllows('team_collaboration'),
            'members_limit' => $quota->members_limit,
        ]);
    }
}
