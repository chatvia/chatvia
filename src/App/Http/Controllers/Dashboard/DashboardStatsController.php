<?php

namespace App\Http\Controllers\Dashboard;

use App\Identity\Workspace\Models\User;
use Domain\Analytics\Data\Dashboard\DashboardStatsData;
use Domain\Analytics\Queries\Dashboard\DashboardStatsQuery;
use Illuminate\Http\Request;

/**
 * JSON numbers for the dashboard home page, for the viewer's workspace and
 * current team.
 */
class DashboardStatsController
{
    public function __invoke(Request $request, DashboardStatsQuery $getStats): DashboardStatsData
    {
        /** @var User $user */
        $user = $request->user();

        return $getStats->get($user, $user->currentOrPrimaryTeam());
    }
}
