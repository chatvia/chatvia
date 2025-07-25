<?php

namespace App\Services\Billing;

use App\Enums\UserCapabilityEnum;
use App\Models\User;

class UserCapabilities
{
    private User $user;

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function can(UserCapabilityEnum $capability): bool
    {
        $planName = strtolower($this->user->plan);

        $plan = config('billing.packages.'.$planName.'.capabilities');

        return $plan[$capability->value] === true;
    }

    public function list()
    {
        $planName = strtolower($this->user->plan);

        return config('billing.packages.'.$planName.'.capabilities');
    }
}
