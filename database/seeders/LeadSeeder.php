<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\Lead;
use Illuminate\Database\Seeder;

class LeadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Only create leads if we have agents
        if (Agent::count() === 0) {
            $this->command->warn('No agents found. Please create some agents first.');

            return;
        }

        $agents = Agent::all();

        // Create leads with different statuses
        foreach ($agents as $agent) {
            // New leads
            Lead::factory(3)
                ->newLead()
                ->create(['agent_id' => $agent->id]);

            // Contacted leads
            Lead::factory(2)
                ->contacted()
                ->create(['agent_id' => $agent->id]);

            // Qualified leads
            Lead::factory(2)
                ->qualified()
                ->create(['agent_id' => $agent->id]);

            // Converted leads
            Lead::factory(1)
                ->converted()
                ->create(['agent_id' => $agent->id]);
        }

        $this->command->info('Created '.Lead::count().' leads across '.$agents->count().' agents.');
    }
}
