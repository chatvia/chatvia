<?php

namespace App\Http\Controllers\Dashboard;

use App\Workspace\Models\User;
use Domain\Dashboard\Actions\GetDashboardStatsAction;
use Illuminate\Http\Request;

class DashboardStatsController
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(Request $request, GetDashboardStatsAction $getStats): array
    {
        /** @var User $user */
        $user = $request->user();

        return $getStats->execute($user, current_team());
    }
}
