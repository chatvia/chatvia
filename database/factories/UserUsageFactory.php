<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Billing\Models\UserUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
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
