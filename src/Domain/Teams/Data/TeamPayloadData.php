<?php

namespace Domain\Teams\Data;

use Spatie\LaravelData\Data;

class TeamPayloadData extends Data
{
    public function __construct(
        /**
         * The team's name.
         */
        public string $name,
        /**
         * Whether members of this team can access the dashboard. Set false for a
         * chat-only team — its members can only chat with the team's agents.
         */
        public bool $can_access_dashboard = true,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'can_access_dashboard' => ['sometimes', 'boolean'],
        ];
    }
}
