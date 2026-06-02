<?php

namespace App\Http\Controllers\Billing\Capabilities;

use App\Billing\Data\Resources\Package\BillingPackageCapabilitiesData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CapabilityController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $capabilities = BillingPackageCapabilitiesData::forPlan($user->plan);

        return response()->json([
            'plan' => $user->plan,
            'capabilities' => $capabilities,
        ]);
    }
}
