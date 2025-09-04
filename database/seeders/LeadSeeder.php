<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Seeder;

class LeadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Make sure we have agents and users
        if (Agent::count() === 0) {
            $this->command->info('No agents found. Please run the database seeder first.');

            return;
        }

        if (User::count() === 0) {
            $this->command->info('No users found. Please run the database seeder first.');

            return;
        }

        $this->command->info('Creating sample leads...');

        // Create leads for each pipeline stage
        $stages = ['new', 'contacted', 'qualified', 'proposal-sent', 'negotiation', 'converted', 'lost'];

        foreach ($stages as $stage) {
            $count = match ($stage) {
                'new' => 8,
                'contacted' => 6,
                'qualified' => 5,
                'proposal-sent' => 4,
                'negotiation' => 3,
                'converted' => 4,
                'lost' => 2,
            };

            Lead::factory($count)->create([
                'pipeline_stage' => $stage,
                'status' => $this->getStatusForStage($stage),
            ]);
        }

        $this->command->info('Created '.Lead::count().' sample leads across all pipeline stages.');
    }

    private function getStatusForStage(string $stage): string
    {
        return match ($stage) {
            'new' => 'new',
            'contacted' => 'contacted',
            'qualified', 'proposal-sent', 'negotiation' => 'qualified',
            'converted' => 'converted',
            'lost' => 'lost',
        };
    }
}
