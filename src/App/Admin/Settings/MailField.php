<?php

declare(strict_types=1);

namespace App\Admin\Settings;

/**
 * One setting an email provider needs ({@see MailProvider::fields()}): the
 * MailSettings property it fills and how to ask for it. The Email settings
 * page and the admin setup both build their inputs from these.
 */
final readonly class MailField
{
    /**
     * @param  'text'|'secret'|'integer'|'choice'  $kind
     * @param  array<string, string>  $options  label by value, for a choice
     */
    private function __construct(
        public string $property,
        public string $label,
        public string $kind,
        public bool $required,
        public ?string $help = null,
        public ?string $placeholder = null,
        public array $options = [],
    ) {}

    public static function text(string $property, string $label, bool $required = false, ?string $help = null, ?string $placeholder = null): self
    {
        return new self($property, $label, 'text', $required, $help, $placeholder);
    }

    /** A key, token or password: never shown again once saved. */
    public static function secret(string $property, string $label, bool $required = true, ?string $help = null): self
    {
        return new self($property, $label, 'secret', $required, $help);
    }

    public static function integer(string $property, string $label, ?string $help = null, ?string $placeholder = null): self
    {
        return new self($property, $label, 'integer', false, $help, $placeholder);
    }

    /**
     * @param  array<string, string>  $options  label by value
     */
    public static function choice(string $property, string $label, array $options, ?string $help = null): self
    {
        return new self($property, $label, 'choice', false, $help, options: $options);
    }
}
