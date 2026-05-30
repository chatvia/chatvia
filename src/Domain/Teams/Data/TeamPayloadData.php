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
