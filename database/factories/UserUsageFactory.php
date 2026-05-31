<?php

namespace Database\Factories;

use App\Billing\Models\UserUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserUsage>
 */
class UserUsageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'messages' => 0,
            'credits' => 0,
            'messages_exceeded' => 0,
        ];
    }
}
