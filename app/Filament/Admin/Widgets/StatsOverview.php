<?php

namespace App\Filament\Admin\Widgets;

use App\Models\Agent;
use App\Models\ChatMessage;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?string $id = 'stats-overview';

    protected static ?int $columns = 3;

    protected function getStats(): array
    {
        $totalUsers = User::count();

        $activeUsers = User::whereHas('subscriptions', function ($query) {
            $query->where('stripe_status', 'active');
        })->orWhere('trial_ends_at', '>', now())->count();

        $totalMessages = ChatMessage::count();
        $totalAgents = Agent::count();

        // Get messages from last 30 days
        $messagesLast30Days = ChatMessage::where('created_at', '>=', now()->subDays(30))->count();

        // Get new users from last 30 days
        $newUsersLast30Days = User::where('created_at', '>=', now()->subDays(30))->count();

        // Get subscription revenue (basic calculation)
        $activeSubscriptions = User::whereHas('subscriptions', function ($query) {
            $query->where('stripe_status', 'active');
        })->count();

        return [
            Stat::make('Total Users', $totalUsers)
                ->description('All registered users')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Active Users', $activeUsers)
                ->description('Users with active subscriptions or trials')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success'),

            Stat::make('New Users (30 days)', $newUsersLast30Days)
                ->description('Users registered in the last 30 days')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('info'),

            Stat::make('Total Messages', number_format($totalMessages))
                ->description('All messages sent through the platform')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('warning'),

            Stat::make('Messages (30 days)', number_format($messagesLast30Days))
                ->description('Messages sent in the last 30 days')
                ->descriptionIcon('heroicon-m-chat-bubble-left')
                ->color('warning'),

            Stat::make('Total Agents', $totalAgents)
                ->description('AI agents created by users')
                ->descriptionIcon('heroicon-m-cpu-chip')
                ->color('success'),

            Stat::make('Active Subscriptions', $activeSubscriptions)
                ->description('Users with active paid subscriptions')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('success'),
        ];
    }
}
