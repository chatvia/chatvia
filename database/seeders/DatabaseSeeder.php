<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Ahmad',
            'email' => 'ahmad@mayahi.net',
        ]);

        Agent::factory()->create([
            'user_id' => $user->id,
            'name' => 'Translation',
            'sub_domain' => 'translation',
            'system_instructions' => config('prompts.knowledge-based-system-instruction'),
            'is_public' => 1,
            'is_active' => 1,
            'type' => 'knowledge',
        ]);
    }
}
