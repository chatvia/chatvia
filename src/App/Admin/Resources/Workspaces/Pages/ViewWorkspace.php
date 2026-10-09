<?php

declare(strict_types=1);

namespace App\Admin\Resources\Workspaces\Pages;

use App\Admin\Resources\Workspaces\WorkspaceResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * The admin panel's read-only view of one workspace account.
 */
class ViewWorkspace extends ViewRecord
{
    #[\Override]
    protected static string $resource = WorkspaceResource::class;
}
