<?php

namespace App\Http\Controllers\Agents\Agents;

use App\Enums\UserCapabilityEnum;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentCreateController
{
    public function __invoke(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('agents/CreateAgent', [
            'can_register_custom_sub_domain' => $user->canPerform(UserCapabilityEnum::CUSTOM_SUB_DOMAIN),
        ]);
    }
}
