<?php

namespace Database\Factories;

use App\Billing\Models\UserAddon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserAddon>
 */
class UserAddonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => 'extra_message_credits',
            'amount' => 1000,
            'stripe_id' => 'cs_test_'.$this->faker->bothify('??????????'),
            'started_at' => now(),
            'ends_at' => now()->addMonth(),
        ];
    }
}
