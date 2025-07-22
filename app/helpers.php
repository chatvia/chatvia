<?php

if (! function_exists('user_has_capability')) {
    /**
     * Check if the current user has a specific capability
     */
    function user_has_capability(string $capability): bool
    {
        $user = auth()->user();

        return $user ? $user->hasCapability($capability) : false;
    }
}

if (! function_exists('user_capability')) {
    /**
     * Get a capability value for the current user
     */
    function user_capability(string $capability, $default = null)
    {
        $user = auth()->user();

        return $user ? $user->getCapability($capability, $default) : $default;
    }
}

if (! function_exists('user_capability_allows')) {
    /**
     * Check if user's capability allows a specific value
     */
    function user_capability_allows(string $capability, $value = null): bool
    {
        $user = auth()->user();

        return $user ? $user->capabilityAllows($capability, $value) : false;
    }
}

if (! function_exists('plan_has_capability')) {
    /**
     * Check if a specific plan has a capability (static check)
     */
    function plan_has_capability(string $planKey, string $capability): bool
    {
        return \App\Support\Billing::packageHasCapability($planKey, $capability);
    }
}

if (! function_exists('get_plan_features')) {
    /**
     * Get UI features for a plan (for display purposes)
     */
    function get_plan_features(string $planKey, bool $isYearly = false): array
    {
        return \App\Support\Billing::getFeaturesForPackage($planKey, $isYearly);
    }
}

if (! function_exists('get_plan_capabilities')) {
    /**
     * Get capabilities for a plan (for logic checks)
     */
    function get_plan_capabilities(string $planKey): array
    {
        return \App\Support\Billing::getCapabilitiesForPackage($planKey);
    }
}
