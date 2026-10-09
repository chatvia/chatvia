<?php

namespace App\Admin\Resources\Workspaces\Pages;

use App\Admin\Resources\Workspaces\WorkspaceResource;
use Filament\Resources\Pages\ListRecords;

/**
 * The admin panel's list of workspace accounts.
 */
class ListWorkspaces extends ListRecords
{
    protected static string $resource = WorkspaceResource::class;
}
