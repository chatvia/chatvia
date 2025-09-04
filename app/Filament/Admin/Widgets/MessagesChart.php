<?php

namespace App\Filament\Admin\Widgets;

use App\Models\ChatMessage;
use Filament\Widgets\ChartWidget;

class MessagesChart extends ChartWidget
{
    protected static ?string $id = 'messages-chart';

    protected static ?string $heading = 'Messages Sent (Last 30 Days)';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $days = collect();
        $messageCounts = collect();

        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $days->push($date->format('M d'));

            $count = ChatMessage::whereDate('created_at', $date)->count();
            $messageCounts->push($count);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Messages',
                    'data' => $messageCounts->toArray(),
                    'backgroundColor' => '#3b82f6',
                    'borderColor' => '#1d4ed8',
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
