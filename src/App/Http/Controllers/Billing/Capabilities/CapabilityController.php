<?php

namespace App\Http\Controllers\Billing\Capabilities;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON list of the plan features the signed-in account has.
 */
class CapabilityController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'plan' => $user->plan,
            // Through the user, so it matches what every gate checks (all on
            // when billing is turned off).
            'capabilities' => $user->capabilities,
        ]);
    }
}
