<?php

namespace App\Http\Controllers\Members;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MembersControllerShow
{
    public function __invoke(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('members/Index', [
            'team_collaboration' => $user->capabilityAllows('team_collaboration'),
            'members_limit' => $user->members_limit,
        ]);
    }
}
