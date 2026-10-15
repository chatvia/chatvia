<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings;

use Closure;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;

/**
 * One card on a Settings page: a service (Google sign-in, Mistral) or a group
 * of options (sign-up and limits). The card is a collapsible section of the
 * page, with its status or a short summary in the header and its fields
 * below ({@see AdminSettingsPage}).
 *
 * Technical options stay out of the way in two ways: a whole card can be
 * advanced (listed last, under "Advanced", and closed), and a card can keep
 * its own technical fields (an address, a rate limit) in a closed "Advanced"
 * part below its everyday fields.
 */
final class SettingsCard
{
    /** @var list<string> The properties the service needs before it works. */
    public array $required = [];

    /** @var list<string>|null The properties the panel edits; null reads them from the fields. */
    public ?array $properties = null;

    public ?string $consoleUrl = null;

    /** The link's text, for a page that is not a provider console (a project page). */
    public ?string $consoleLabel = null;

    /** @var array<string, string|array{0: string, 1: array<string, string>}> Route name (or name and parameters) by label: URLs to register with the provider. */
    public array $callbackRoutes = [];

    /** @var (Closure(): string)|null A line about the values in use, shown on the card. */
    public ?Closure $summary = null;

    /** @var (Closure(): string)|null The color of a group of options' summary, such as "warning" while a change waits; null is gray. */
    public ?Closure $tone = null;

    /** @var (Closure(): bool)|null */
    public ?Closure $visible = null;

    /** Whether the whole card is technical: listed last, under "Advanced", and closed. */
    public bool $advanced = false;

    /** @var (Closure(): list<Component>)|null The fields of the card's closed "Advanced" part. */
    public ?Closure $advancedFields = null;

    /** @var Closure|null When the "Advanced" part shows (Filament injects `Get`); null always. */
    public ?Closure $advancedVisible = null;

    /** @var (Closure(): array<string, bool>)|null Small labels under the description (what a service does), each marked when in use. */
    public ?Closure $chips = null;

    /**
     * The help file behind the card's "?" button, when cards built by one
     * helper (a provider per card) share it; null uses the card's key. See
     * {@see AdminSettingsPage::helpOf()}.
     */
    public ?string $helpFile = null;

    /**
     * @param  Closure(): list<Component>  $fields  The fields of the panel.
     */
    private function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly string $description,
        public readonly string|Heroicon $icon,
        public readonly Closure $fields,
    ) {}

    /**
     * @param  Closure(): list<Component>  $fields
     */
    public static function make(string $key, string $title, string $description, string|Heroicon $icon, Closure $fields): self
    {
        return new self($key, $title, $description, $icon, $fields);
    }

    public function required(string ...$properties): self
    {
        $this->required = array_values($properties);

        return $this;
    }

    public function properties(string ...$properties): self
    {
        $this->properties = array_values($properties);

        return $this;
    }

    public function console(?string $url, ?string $label = null): self
    {
        $this->consoleUrl = $url;
        $this->consoleLabel = $label;

        return $this;
    }

    /**
     * @param  array<string, string|array{0: string, 1: array<string, string>}>  $routes  Route name, or name and parameters, by label.
     */
    public function callbacks(array $routes): self
    {
        $this->callbackRoutes = $routes;

        return $this;
    }

    /**
     * @param  Closure(): string  $summary
     */
    public function summary(Closure $summary): self
    {
        $this->summary = $summary;

        return $this;
    }

    /**
     * @param  Closure(): string  $tone
     */
    public function tone(Closure $tone): self
    {
        $this->tone = $tone;

        return $this;
    }

    /**
     * @param  Closure(): bool  $visible
     */
    public function visible(Closure $visible): self
    {
        $this->visible = $visible;

        return $this;
    }

    public function advanced(bool $advanced = true): self
    {
        $this->advanced = $advanced;

        return $this;
    }

    /**
     * @param  Closure(): list<Component>  $fields
     */
    public function advancedFields(Closure $fields, ?Closure $visible = null): self
    {
        $this->advancedFields = $fields;
        $this->advancedVisible = $visible;

        return $this;
    }

    /**
     * @param  Closure(): array<string, bool>  $chips  Whether each label is in use, by label.
     */
    public function chips(Closure $chips): self
    {
        $this->chips = $chips;

        return $this;
    }

    /** Share one help file between the cards a helper builds, with `{title}` filled in per card. */
    public function helpFile(string $name): self
    {
        $this->helpFile = $name;

        return $this;
    }
}
