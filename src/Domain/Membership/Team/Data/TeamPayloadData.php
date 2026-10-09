<?php

namespace Domain\Membership\Team\Data;

use Spatie\LaravelData\Data;

/**
 * Public-API request body for creating or renaming a team.
 */
class TeamPayloadData extends Data
{
    public function __construct(
        /**
         * The team's name.
         */
        public string $name,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }
}
