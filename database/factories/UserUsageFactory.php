<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Billing\Models\UserUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds an empty usage row (no messages, credits or tokens used) for a credit
 * period. The caller sets `user_credit_id`.
 *
 * @extends Factory<UserUsage>
 */
class UserUsageFactory extends Factory
{
    #[\Override]
    protected $model = UserUsage::class;

    public function definition(): array
    {
        return [
            'messages' => 0,
            'credits' => 0,
            'messages_exceeded' => 0,
            'tokens' => 0,
        ];
    }
}
