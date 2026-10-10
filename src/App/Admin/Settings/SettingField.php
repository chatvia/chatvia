<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Closure;
use DateTimeImmutable;

/**
 * One value an admin can set in a settings group, and the config key it fills.
 *
 * Values are saved as strings in the group's encrypted payload. {@see cast()}
 * turns a saved string into the PHP type the config readers expect (a toggle
 * saved as "0" becomes false), and {@see toPayload()} turns panel input back
 * into a string. Secrets are write-only in the panel: they are never sent back
 * to the browser, and a blank input keeps the saved value.
 */
final readonly class SettingField
{
    /**
     * @param  list<string>  $rules  Extra validation rules for a filled text value.
     * @param  bool|int|string|(Closure(array<string, bool|int|string|null>): (bool|int|string|null))|null  $default  The value while nothing is saved. A closure gets the values of the fields before this one, by name.
     * @param  array<string, string>  $options  The choices of a select field: value => label.
     * @param  array<string, Closure(bool|int|string|null): mixed>  $alsoFills  More config keys this value fills, each with the way to derive it.
     */
    private function __construct(
        public string $name,
        public string $label,
        public string $config,
        public SettingFieldKind $kind,
        public bool $required = false,
        public array $rules = [],
        public ?string $help = null,
        private bool|int|string|Closure|null $default = null,
        public array $options = [],
        public ?int $min = null,
        public ?int $max = null,
        public array $alsoFills = [],
    ) {}

    /**
     * @param  list<string>  $rules
     */
    public static function text(string $name, string $label, string $config, bool $required = false, array $rules = [], ?string $help = null): self
    {
        return new self($name, $label, $config, SettingFieldKind::Text, $required, $rules, $help);
    }

    /**
     * @param  list<string>  $rules
     */
    public static function secret(string $name, string $label, string $config, bool $required = true, array $rules = [], ?string $help = null): self
    {
        return new self($name, $label, $config, SettingFieldKind::Secret, $required, $rules, $help);
    }

    /**
     * A URL of this app, such as an OAuth redirect, that defaults to $defaultPath.
     */
    public static function url(string $name, string $label, string $config, string $defaultPath, ?string $help = null): self
    {
        return new self($name, $label, $config, SettingFieldKind::Url, rules: ['url'], help: $help,
            default: fn (): string => url($defaultPath));
    }

    /**
     * An on/off switch. The default may depend on an earlier field of the
     * group, for example "on while billing is off".
     *
     * @param  bool|Closure(array<string, bool|int|string|null>): bool  $default
     */
    public static function toggle(string $name, string $label, string $config, bool|Closure $default, ?string $help = null): self
    {
        return new self($name, $label, $config, SettingFieldKind::Toggle, help: $help, default: $default);
    }

    public static function integer(string $name, string $label, string $config, int $default, int $min, ?int $max = null, ?string $help = null): self
    {
        return new self($name, $label, $config, SettingFieldKind::Integer, help: $help, default: $default, min: $min, max: $max);
    }

    /**
     * @param  array<string, string>  $options  value => label
     * @param  array<string, Closure(bool|int|string|null): mixed>  $alsoFills  config key => how to derive its value from this one
     */
    public static function select(string $name, string $label, string $config, array $options, string $default, ?string $help = null, array $alsoFills = []): self
    {
        return new self($name, $label, $config, SettingFieldKind::Select, help: $help, default: $default, options: $options, alsoFills: $alsoFills);
    }

    /**
     * A calendar day, put into config as `Y-m-d`. Null while nothing is saved.
     */
    public static function date(string $name, string $label, string $config, ?string $help = null): self
    {
        return new self($name, $label, $config, SettingFieldKind::Date, help: $help);
    }

    public function isSecret(): bool
    {
        return $this->kind === SettingFieldKind::Secret;
    }

    /**
     * The value used while nothing is saved.
     *
     * @param  array<string, bool|int|string|null>  $earlier  The values of the fields before this one, by name.
     */
    public function defaultValue(array $earlier = []): bool|int|string|null
    {
        return $this->default instanceof Closure ? ($this->default)($earlier) : $this->default;
    }

    /**
     * A saved value as the type the config readers expect. Null when it is
     * blank or not valid for this field: then the default applies.
     */
    public function cast(mixed $saved): bool|int|string|null
    {
        if (is_bool($saved)) {
            $saved = $saved ? '1' : '0';
        }

        if (! is_scalar($saved) || trim((string) $saved) === '') {
            return null;
        }

        $saved = (string) $saved;

        return match ($this->kind) {
            SettingFieldKind::Toggle => filter_var($saved, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            SettingFieldKind::Integer => $this->integerInRange($saved),
            SettingFieldKind::Select => array_key_exists($saved, $this->options) ? $saved : null,
            SettingFieldKind::Date => $this->calendarDay($saved),
            SettingFieldKind::Text, SettingFieldKind::Secret, SettingFieldKind::Url => $saved,
        };
    }

    /**
     * The panel input as the string to save, or null when it is blank. A
     * toggle that is off is saved as "0", so it does not fall back to its
     * default.
     */
    public function toPayload(mixed $input): ?string
    {
        if ($this->kind === SettingFieldKind::Toggle) {
            return $input === null ? null : (filter_var($input, FILTER_VALIDATE_BOOL) ? '1' : '0');
        }

        $value = is_scalar($input) ? trim((string) $input) : '';

        return $value === '' ? null : $value;
    }

    private function integerInRange(string $saved): ?int
    {
        $value = filter_var($saved, FILTER_VALIDATE_INT);

        if ($value === false
            || ($this->min !== null && $value < $this->min)
            || ($this->max !== null && $value > $this->max)) {
            return null;
        }

        return $value;
    }

    private function calendarDay(string $saved): ?string
    {
        $day = DateTimeImmutable::createFromFormat('!Y-m-d', $saved);

        return $day !== false && $day->format('Y-m-d') === $saved ? $saved : null;
    }
}
