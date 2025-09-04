<?php

namespace Database\Seeders;

use App\Models\PipelineStage;
use Illuminate\Database\Seeder;

class PipelineStageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stages = [
            [
                'name' => 'New',
                'slug' => 'new',
                'color' => '#3B82F6', // blue
                'order' => 1,
                'is_default' => true,
                'description' => 'Newly captured leads that need initial review',
            ],
            [
                'name' => 'Contacted',
                'slug' => 'contacted',
                'color' => '#EAB308', // yellow
                'order' => 2,
                'description' => 'Leads that have been contacted but not yet qualified',
            ],
            [
                'name' => 'Qualified',
                'slug' => 'qualified',
                'color' => '#10B981', // green
                'order' => 3,
                'description' => 'Leads that have been qualified as potential customers',
            ],
            [
                'name' => 'Proposal Sent',
                'slug' => 'proposal-sent',
                'color' => '#F59E0B', // amber
                'order' => 4,
                'description' => 'Qualified leads who have received a proposal or quote',
            ],
            [
                'name' => 'Negotiation',
                'slug' => 'negotiation',
                'color' => '#8B5CF6', // violet
                'order' => 5,
                'description' => 'Leads in active negotiation or discussion phase',
            ],
            [
                'name' => 'Converted',
                'slug' => 'converted',
                'color' => '#059669', // emerald
                'order' => 6,
                'description' => 'Leads that have been successfully converted to customers',
            ],
            [
                'name' => 'Lost',
                'slug' => 'lost',
                'color' => '#DC2626', // red
                'order' => 7,
                'description' => 'Leads that did not convert or are no longer viable',
            ],
        ];

        foreach ($stages as $stage) {
            PipelineStage::updateOrCreate(
                ['slug' => $stage['slug']],
                $stage
            );
        }
    }
}
