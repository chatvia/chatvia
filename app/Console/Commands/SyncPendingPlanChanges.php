<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SyncPendingPlanChanges extends Command
{
    protected $signature = 'billing:sync-pending-changes {--user= : Sync for specific user ID}';
    
    protected $description = 'Sync pending plan changes from Stripe metadata';

    public function handle()
    {
        $userId = $this->option('user');
        
        if ($userId) {
            $this->syncUserPendingChanges($userId);
        } else {
            $this->syncAllUsersPendingChanges();
        }
    }

    private function syncUserPendingChanges($userId)
    {
        $user = User::find($userId);
        
        if (!$user) {
            $this->error("User with ID {$userId} not found.");
            return;
        }
        
        if (!$user->subscribed()) {
            $this->info("User {$userId} has no active subscription.");
            return;
        }

        $pendingChange = $user->syncPendingPlanChanges();
        
        if ($pendingChange) {
            $this->info("User {$userId}: Pending change from {$pendingChange['from_package']} to {$pendingChange['to_package']}");
        } else {
            $this->info("User {$userId}: No pending changes");
        }
    }

    private function syncAllUsersPendingChanges()
    {
        $users = User::whereHas('subscriptions', function ($query) {
            $query->where('stripe_status', 'active');
        })->get();

        $this->info("Syncing pending plan changes for {$users->count()} users...");
        
        $bar = $this->output->createProgressBar($users->count());
        $pendingCount = 0;

        foreach ($users as $user) {
            $pendingChange = $user->syncPendingPlanChanges();
            
            if ($pendingChange) {
                $pendingCount++;
                $this->newLine();
                $this->info("User {$user->id}: {$pendingChange['from_package']} → {$pendingChange['to_package']}");
            }
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Sync complete. Found {$pendingCount} users with pending plan changes.");
    }
} 