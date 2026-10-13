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

    /** @var (Closure(): bool)|null */
    public ?Closure $visible = null;

    /** Whether the card starts closed, for options most admins never need. */
    public bool $collapsed = false;

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
     * @param  Closure(): bool  $visible
     */
    public function visible(Closure $visible): self
    {
        $this->visible = $visible;

        return $this;
    }

    public function collapsed(bool $collapsed = true): self
    {
        $this->collapsed = $collapsed;

        return $this;
    }

    /** Share one help file between the cards a helper builds, with `{title}` filled in per card. */
    public function helpFile(string $name): self
    {
        $this->helpFile = $name;

        return $this;
    }
}
