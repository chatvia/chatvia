<?php

namespace App\Admin\Settings;

/**
 * One value an admin can set in a settings group, and the config key it fills.
 * Secrets are write-only in the panel: they are never sent back to the
 * browser, and a blank input keeps the saved value.
 */
final readonly class SettingField
{
    /**
     * @param  list<string>  $rules  Extra validation rules for a filled value.
     * @param  string|null  $defaultPath  A path in this app whose full URL is used while nothing is saved.
     */
    private function __construct(
        public string $name,
        public string $label,
        public string $config,
        public bool $secret,
        public bool $required,
        public array $rules,
        public ?string $help,
        public ?string $defaultPath = null,
    ) {}

    /**
     * @param  list<string>  $rules
     */
    public static function text(string $name, string $label, string $config, bool $required = false, array $rules = [], ?string $help = null): self
    {
        return new self($name, $label, $config, secret: false, required: $required, rules: $rules, help: $help);
    }

    /**
     * @param  list<string>  $rules
     */
    public static function secret(string $name, string $label, string $config, bool $required = true, array $rules = [], ?string $help = null): self
    {
        return new self($name, $label, $config, secret: true, required: $required, rules: $rules, help: $help);
    }

    /**
     * A URL of this app, such as an OAuth redirect, that defaults to $defaultPath.
     */
    public static function url(string $name, string $label, string $config, string $defaultPath, ?string $help = null): self
    {
        return new self($name, $label, $config, secret: false, required: false, rules: ['url'], help: $help, defaultPath: $defaultPath);
    }

    /**
     * The value used while nothing is saved.
     */
    public function defaultValue(): ?string
    {
        return $this->defaultPath === null ? null : url($this->defaultPath);
    }
}
