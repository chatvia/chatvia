<?php

declare(strict_types=1);

namespace Support\Localization\Country\Data;

use JsonSerializable;

/**
 * One entry of a country picker: the ISO code the form submits and the name
 * shown to the user, already in their language.
 */
final readonly class CountryOptionData implements JsonSerializable
{
    public function __construct(
        public string $code,
        public string $name,
    ) {}

    /** @return array{code: string, name: string} */
    public function jsonSerialize(): array
    {
        return ['code' => $this->code, 'name' => $this->name];
    }
}
