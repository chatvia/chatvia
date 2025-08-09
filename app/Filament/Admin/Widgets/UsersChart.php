<?php

namespace App\Filament\Admin\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;

class UsersChart extends ChartWidget
{
    protected static ?string $id = 'users-chart';

    protected static ?string $heading = 'User Registrations (Last 30 Days)';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $days = collect();
        $userCounts = collect();

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $days->push($date->format('M d'));

            $count = User::whereDate('created_at', $date)->count();
            $userCounts->push($count);
        }

        return [
            'datasets' => [
                [
                    'label' => 'New Users',
                    'data' => $userCounts->toArray(),
                    'backgroundColor' => '#10b981',
                    'borderColor' => '#059669',
                ],
            ],
            'labels' => $days->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
