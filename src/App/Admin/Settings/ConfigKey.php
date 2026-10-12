<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Attribute;

/**
 * The config key a settings property fills, so the code that reads
 * `config('services.…')` or `config('chatvia.…')` gets the value saved in the
 * admin panel with no change ({@see SettingsConfig}). A blank property leaves
 * the key at its value from config/*.php. A property may fill more than one
 * key.
 *
 * `defaultPath` is for URLs of this app, such as an OAuth redirect: while
 * nothing is saved the key gets `url($defaultPath)`.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final readonly class ConfigKey
{
    public function __construct(
        public string $key,
        public ?string $defaultPath = null,
    ) {}
}
