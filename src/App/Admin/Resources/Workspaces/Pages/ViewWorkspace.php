<?php

namespace App\Admin\Resources\Workspaces\Pages;

use App\Admin\Resources\Workspaces\WorkspaceResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * The admin panel's read-only view of one workspace account.
 */
class ViewWorkspace extends ViewRecord
{
    protected static string $resource = WorkspaceResource::class;
}
