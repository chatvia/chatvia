<?php

declare(strict_types=1);

namespace App\Admin\Resources\Workspaces\Pages;

use App\Admin\Resources\Workspaces\WorkspaceResource;
use Filament\Resources\Pages\ListRecords;

/**
 * The admin panel's list of workspace accounts.
 */
class ListWorkspaces extends ListRecords
{
    #[\Override]
    protected static string $resource = WorkspaceResource::class;
}
