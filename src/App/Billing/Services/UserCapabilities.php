<?php

namespace App\Billing\Services;

use App\Workspace\Models\User;
use App\Billing\Data\Resources\Package\BillingPackageCapabilitiesData;
use App\Billing\Enums\CapabilityEnum;

class UserCapabilities
{
    private User $user;

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function can(CapabilityEnum $capability): bool
    {
        $capabilities = $this->dto();

        return $capabilities->{$capability->value} === true;
    }

    public function dto(): BillingPackageCapabilitiesData
    {
        return BillingPackageCapabilitiesData::forPlan($this->user->plan);
    }

    public function list(): array
    {
        return $this->dto()->toArray();
    }
}
