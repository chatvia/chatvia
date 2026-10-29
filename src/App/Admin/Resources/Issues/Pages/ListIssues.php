<?php

declare(strict_types=1);

namespace App\Admin\Resources\Issues\Pages;

use App\Admin\Resources\Issues\IssueResource;
use Filament\Resources\Pages\ListRecords;

/** Radar's list: open issues, newest first, refreshed every 30 seconds. */
class ListIssues extends ListRecords
{
    #[\Override]
    protected static string $resource = IssueResource::class;

    #[\Override]
    protected static ?string $title = 'Radar';

    public function getSubheading(): string
    {
        return 'Radar watches the services this install relies on, such as the AI providers, email and backups, and lists what you need to fix: a refused API key, an account out of credit, a model that is gone, a backup that failed. Most issues close by themselves once the service works again.';
    }
}
