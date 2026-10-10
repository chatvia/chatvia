<?php

namespace App\Http\Rules;

use Closure;
use Domain\Agent\Models\Agent;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An agent's chat-address slug (`/chat/{slug}`): lowercase letters, digits and
 * inner dashes, 3–63 characters, and free. Free means no other agent uses it
 * as its slug or as its external id, because both name a chat in the address.
 */
final readonly class AgentSlug implements ValidationRule
{
    private const string PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function __construct(
        private Agent $agent,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || strlen($value) < 3 || strlen($value) > 63 || preg_match(self::PATTERN, $value) !== 1) {
            $fail('validation.regex')->translate();

            return;
        }

        $taken = Agent::query()
            ->whereKeyNot($this->agent->getKey())
            ->where(fn ($query) => $query->where('slug', $value)->orWhere('external_id', $value))
            ->exists();

        if ($taken) {
            $fail('validation.unique')->translate();
        }
    }
}
