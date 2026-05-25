<?php

namespace App\Console\Commands;

use App\Auth\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Description('Cache message usage')]
#[Signature('app:cache-message-usage')]
class CacheMessageUsage extends Command
{
    public function handle(): void
    {
        User::chunk(100, function ($users): void {
            /** @var User $user */
            foreach ($users as $user) {
                $user->creditSystem()->refreshCanSendMessages();
            }
        });
    }
}
