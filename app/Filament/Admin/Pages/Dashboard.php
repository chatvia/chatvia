<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Widgets\MessagesChart;
use App\Filament\Admin\Widgets\StatsOverview;
use App\Filament\Admin\Widgets\UsersChart;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    // Prevent base page from rendering panel-level discovered widgets as a third set
    public function getWidgets(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            StatsOverview::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 3;
    }

    protected function getFooterWidgets(): array
    {
        return [
            MessagesChart::class,
            UsersChart::class,
        ];
    }

    public function getFooterWidgetsColumns(): int|string|array
    {
        return 2;
    }
}
