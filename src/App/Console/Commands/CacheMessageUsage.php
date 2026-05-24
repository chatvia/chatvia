<?php

namespace App\Console\Commands;

use App\Auth\Models\User;
use Illuminate\Console\Command;

class CacheMessageUsage extends Command
{
    protected $signature = 'app:cache-message-usage';

    protected $description = 'Cache message usage';

    public function handle(): void
    {
        User::chunk(100, function ($users) {
            /** @var User $user */
            foreach ($users as $user) {
                $user->creditSystem()->refreshCanSendMessages();
            }
        });
    }
}
