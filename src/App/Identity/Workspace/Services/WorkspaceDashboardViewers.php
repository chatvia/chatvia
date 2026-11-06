<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Services;

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Spectra\Contracts\DashboardTrackableResolver;

/**
 * Who may open a workspace's dashboard in Spectra ({@see WorkspaceDashboards}):
 * the workspace's owner only, because it shows what the install pays for AI. A
 * teammate is refused (403). Someone from another workspace gets a 404, as for
 * any record of another workspace, so a forwarded link confirms nothing.
 */
class WorkspaceDashboardViewers implements DashboardTrackableResolver
{
    public function resolve(Authenticatable $viewer, string $type, string $id): ?Model
    {
        abort_unless($viewer instanceof User && $type === new Workspace()->getMorphClass(), 404);

        $workspace = $viewer->workspaceMembership?->workspace;

        abort_unless((string) $workspace?->getKey() === $id, 404);

        return $viewer->isWorkspaceOwner() ? $workspace : null;
    }
}
