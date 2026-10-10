<?php

declare(strict_types=1);

namespace App\Admin\Settings;

/**
 * What kind of value a setting field holds. It decides the input the panel
 * shows and the PHP type the saved value has in config.
 */
enum SettingFieldKind
{
    case Text;

    /** Write-only: never sent back to the browser. */
    case Secret;

    /** A URL of this app with a default, such as an OAuth redirect. */
    case Url;

    case Toggle;
    case Integer;

    /** One of a fixed list of options. */
    case Select;

    /** A calendar day, `Y-m-d`. */
    case Date;

    /**
     * A typed value always has one (the saved one or its default), so the
     * panel shows it in the form and config gets it as its PHP type.
     */
    public function isTyped(): bool
    {
        return match ($this) {
            self::Toggle, self::Integer, self::Select, self::Date => true,
            self::Text, self::Secret, self::Url => false,
        };
    }
}
