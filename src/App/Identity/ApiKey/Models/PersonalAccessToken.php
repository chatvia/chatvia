<?php

namespace App\Identity\ApiKey\Models;

use Carbon\CarbonInterface;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * An API key (`personal_access_tokens`). Sanctum's own model, except that it
 * writes `last_used_at` at most once a minute: Sanctum saves it on every API
 * request, which would rewrite the token row for every call a busy client
 * makes. The minute-level time is all the API keys page shows.
 *
 * Same table and no morph alias of its own: only the tokenable side
 * (`tokenable_type`) is polymorphic.
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /** How fresh a stored `last_used_at` may be before a new one is written. */
    private const int LAST_USED_WRITE_SECONDS = 60;

    /**
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        if ($this->isRecentLastUsedTouch()) {
            // Keep the stored times, so the model stays clean.
            foreach (array_keys($this->getDirty()) as $key) {
                $this->attributes[$key] = $this->original[$key] ?? null;
            }

            return true;
        }

        return parent::save($options);
    }

    /**
     * Whether this save only moves `last_used_at` (and `updated_at`) while the
     * stored `last_used_at` is still under a minute old.
     */
    private function isRecentLastUsedTouch(): bool
    {
        $dirty = collect($this->getDirty())->keys();

        if (! $this->exists || $dirty->isEmpty() || $dirty->diff(['last_used_at', 'updated_at'])->isNotEmpty()) {
            return false;
        }

        $stored = $this->getOriginal('last_used_at');

        return $stored instanceof CarbonInterface
            && $stored->greaterThan(now()->subSeconds(self::LAST_USED_WRITE_SECONDS));
    }
}
