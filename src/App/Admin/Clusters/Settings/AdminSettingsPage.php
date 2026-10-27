<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings;

use App\Admin\Clusters\Settings\Data\SettingsSearchEntryData;
use App\Admin\Settings\AdminSettings;
use App\Admin\Settings\SettingsConfig;
use App\Admin\Settings\SettingStatus;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Icon;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\Size;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Support\Http\PublicUrl;
use Throwable;

/**
 * One page of the admin panel's Settings, for one settings class: every card
 * ({@see SettingsCard}) as a collapsible section with its icon, description,
 * a service's status, a link to its docs, and its fields, each with a "?"
 * that explains it in plain words; one Save button that stays in view, and
 * an outline that jumps to a section. The everyday cards come first;
 * technical cards follow under an "Advanced" heading, closed. The rules
 * every page shares:
 *
 * - Secrets are write-only. The panel never receives a saved secret, a blank
 *   input keeps it, and the trash button next to the input removes it.
 * - A blank field uses its default, shown as the placeholder. A toggle or a
 *   choice that still equals its default is not saved, so the default (which
 *   .env may preset) keeps applying.
 *
 * A change applies right away, also to the queue workers (see
 * {@see SettingsConfig}).
 *
 * @property-read Schema $form
 */
abstract class AdminSettingsPage extends Page
{
    #[\Override]
    protected static ?string $cluster = SettingsCluster::class;

    /** @var class-string<AdminSettings> */
    protected static string $settings;

    /** @var array<string, mixed>|null The form of every card. */
    public ?array $data = [];

    /** One sentence about the page, also shown on its card in the overview. */
    abstract public static function description(): string;

    /**
     * @return list<SettingsCard>
     */
    abstract protected function cards(): array;

    public function mount(): void
    {
        $this->fillCards();
    }

    public function getSubheading(): string
    {
        return static::description();
    }

    #[\Override]
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(['default' => 1, 'xl' => 5])
                ->extraAttributes(['class' => 'cv-settings-layout'])
                ->schema([
                    View::make('filament.settings.outline')
                        ->viewData(fn (): array => ['items' => array_map($this->outlineItem(...), $this->visibleCards())]),
                    Form::make([EmbeddedSchema::make('form')])
                        ->id('form')
                        ->livewireSubmitHandler('save')
                        ->footer([
                            Actions::make([
                                Action::make('save')
                                    ->label('Save changes')
                                    ->submit('save')
                                    ->keyBindings(['mod+s']),
                            ])
                                ->sticky()
                                ->key('form-actions'),
                        ])
                        ->columnSpan(['xl' => 4]),
                ]),
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    /**
     * A section per card: its fields, and the URLs to register with its
     * provider. The advanced cards follow under their own heading.
     */
    public function form(Schema $schema): Schema
    {
        $cards = $this->visibleCards();
        $everyday = array_filter($cards, fn (SettingsCard $card): bool => ! $card->advanced);
        $advanced = array_filter($cards, fn (SettingsCard $card): bool => $card->advanced);

        return $schema->components([
            ...array_map($this->section(...), array_values($everyday)),
            ...($advanced === [] ? [] : [View::make('filament.settings.advanced')]),
            ...array_map($this->section(...), array_values($advanced)),
        ]);
    }

    public function save(): void
    {
        $this->saveSettings();

        Notification::make()->success()->title('Settings saved')->send();
    }

    /** Saves the panel's input, for a page that tells about the save its own way. */
    protected function saveSettings(): void
    {
        $this->settings()->fill($this->valuesToSave($this->form->getState()))->save();

        // Refill, so the secret inputs that were just saved are empty again
        // and a card that a change shows (or hides) has its values.
        $this->fillCards();
    }

    /**
     * The outline replaces the cluster's side navigation: the breadcrumb
     * leads back to the overview.
     *
     * @return array<mixed>
     */
    #[\Override]
    public function getSubNavigation(): array
    {
        return [];
    }

    /** The heading of the page's group on the overview. */
    public static function overviewGroup(): string
    {
        return 'Platform and brand';
    }

    /**
     * The status of the page's tile on the overview, and its tone: the tile
     * shows a dot in the tone, and the status when the dot is pointed at. A
     * service that is half set up comes first. A page whose everyday cards
     * are all optional services (sign-in buttons, channels, connectors) says
     * how many are set up; any other page how many options differ from the
     * defaults.
     *
     * @return array{0: string, 1: string}
     */
    public function overviewStatus(): array
    {
        if (($unfinished = $this->unfinishedStatus()) !== null) {
            return $unfinished;
        }

        $cards = $this->visibleCards();
        $everyday = array_filter($cards, fn (SettingsCard $card): bool => ! $card->advanced);
        $services = array_filter($everyday, fn (SettingsCard $card): bool => $card->required !== []);

        if ($services !== [] && count($services) === count($everyday)) {
            $ready = count(array_filter($services, fn (SettingsCard $card): bool => $this->statusOf($card) === SettingStatus::Configured));

            return $ready === 0 ? ['Optional: none set up yet', 'gray'] : ["{$ready} set up", 'success'];
        }

        $changed = count(array_filter(
            array_merge(...array_map($this->propertiesOf(...), $cards)),
            $this->isSaved(...),
        ));

        return $changed === 0 ? ['Using the defaults', 'gray'] : ["{$changed} ".Str::plural('change', $changed).' from the defaults', 'primary'];
    }

    /**
     * A warning when a service on the page is half set up (a client ID
     * without its secret), naming it; null when none is.
     *
     * @return array{0: string, 1: string}|null
     */
    protected function unfinishedStatus(): ?array
    {
        $unfinished = array_values(array_filter(
            $this->visibleCards(),
            fn (SettingsCard $card): bool => $card->required !== [] && $this->statusOf($card) === SettingStatus::Incomplete,
        ));

        return match (count($unfinished)) {
            0 => null,
            1 => ["{$unfinished[0]->title} is not finished", 'warning'],
            default => [count($unfinished).' services are not finished', 'warning'],
        };
    }

    /**
     * Whether a field shows the value in use rather than a blank field with
     * the default as its placeholder: for inputs that cannot show a
     * placeholder, such as buttons or radio lists.
     */
    protected function showsValueInUse(string $property): bool
    {
        return false;
    }

    protected function settings(): AdminSettings
    {
        return app(static::$settings);
    }

    /** The value a property has while nothing is saved. */
    protected function defaultValue(string $property): mixed
    {
        return app(SettingsConfig::class)->defaultOf(static::$settings, $property);
    }

    /** The value in use: the saved one, or the default. */
    protected function valueInUse(string $property): mixed
    {
        return $this->settings()->{$property} ?? $this->defaultValue($property);
    }

    protected function isSaved(string $property): bool
    {
        return filled($this->settings()->{$property});
    }

    // --- Cards ---------------------------------------------------------------

    /**
     * @return list<SettingsCard>
     */
    protected function visibleCards(): array
    {
        $cards = array_filter($this->cards(), fn (SettingsCard $card): bool => ! $card->visible instanceof Closure || ($card->visible)());

        return [
            ...array_values(array_filter($cards, fn (SettingsCard $card): bool => ! $card->advanced)),
            ...array_values(array_filter($cards, fn (SettingsCard $card): bool => $card->advanced)),
        ];
    }

    /**
     * What the Settings search finds on this page: the page itself, and each
     * card it shows with the labels of its options, linked to its section
     * ({@see SettingsSearch}).
     *
     * @return list<SettingsSearchEntryData>
     */
    public function searchEntries(): array
    {
        $page = (string) static::getNavigationLabel();

        return [
            new SettingsSearchEntryData(
                title: $page,
                page: null,
                description: static::description(),
                url: static::getUrl(),
                icon: static::getNavigationIcon(),
            ),
            ...array_map(fn (SettingsCard $card): SettingsSearchEntryData => new SettingsSearchEntryData(
                title: $card->title,
                page: $page,
                description: $card->description,
                url: static::cardUrl($card->key),
                icon: $card->icon,
                options: $this->optionLabels($card),
                pageDescription: static::description(),
            ), $this->visibleCards()),
        ];
    }

    /**
     * The labels of a card's options, its "Advanced" part included.
     *
     * @return list<string>
     */
    private function optionLabels(SettingsCard $card): array
    {
        return array_values(array_unique($this->labelsIn($this->fieldsOf($card))));
    }

    /**
     * The labels of the fields among these components and inside them (a
     * grid, a group of fields). A label or a group that needs the form's
     * state cannot be read outside the page; it is left out of the search.
     *
     * @param  array<mixed>  $components
     * @return list<string>
     */
    private function labelsIn(array $components): array
    {
        $labels = [];

        foreach ($components as $component) {
            if (! $component instanceof Component) {
                continue;
            }

            try {
                $label = $component instanceof Field ? Str::of((string) $component->getLabel())->stripTags()->squish()->toString() : '';
                $children = $component->getDefaultChildComponents();
            } catch (Throwable) {
                continue;
            }

            if (filled($label)) {
                $labels[] = $label;
            }

            if (is_array($children)) {
                array_push($labels, ...$this->labelsIn($children));
            }
        }

        return $labels;
    }

    /** A visible card by its key. */
    protected function card(string $key): ?SettingsCard
    {
        return array_find($this->visibleCards(), fn (SettingsCard $card): bool => $card->key === $key);
    }

    protected function fillCards(): void
    {
        $this->form->fill($this->formValues(array_merge(...array_map($this->propertiesOf(...), $this->visibleCards()))));
    }

    /**
     * The page opened on one card: the page opens the card, scrolls to it and
     * rings it (resources/views/filament/settings/outline.blade.php).
     */
    public static function cardUrl(string $key): string
    {
        return static::getUrl().'#'.static::sectionIdFor($key);
    }

    /** The HTML id of a card's section, unique across the Settings pages. */
    private function sectionId(SettingsCard $card): string
    {
        return static::sectionIdFor($card->key);
    }

    protected static function sectionIdFor(string $key): string
    {
        return static::getSlug()."-{$key}";
    }

    /**
     * One card as a collapsible section: a service's status (or a warning),
     * the provider console and the docs in its header, its fields, the URLs
     * to register with the provider, and its closed "Advanced" part. An
     * advanced card always starts closed; an everyday card remembers whether
     * the admin closed it.
     */
    private function section(SettingsCard $card): Section
    {
        return Section::make($card->title)
            ->id($this->sectionId($card))
            ->description(fn (): string|HtmlString => $this->descriptionOf($card))
            ->icon($card->icon)
            ->extraAttributes(['class' => 'cv-settings-card '.IconTone::cssClass($card->icon).($card->advanced ? ' cv-settings-card--advanced' : '')])
            ->collapsible()
            ->collapsed($card->advanced)
            ->persistCollapsed(! $card->advanced)
            ->afterHeader([
                // A service's status is a badge. A group of options shows its
                // line only as a warning (a change that waits), not the
                // options in use. Two lines of it at most, the tooltip all of it.
                Text::make(fn (): string => $this->statusLine($card)[0])
                    ->badge($card->required !== [])
                    ->size(TextSize::Small)
                    ->color(fn (): string => $this->statusLine($card)[1])
                    ->tooltip(fn (): ?string => Str::length($this->statusLine($card)[0]) > 60 ? $this->statusLine($card)[0] : null)
                    ->visible(fn (): bool => $card->required !== [] || $this->statusLine($card)[1] !== 'gray'),
                ...($card->consoleUrl === null ? [] : [
                    Action::make("console_{$card->key}")
                        ->label($card->consoleLabel ?? 'Open console')
                        ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->iconPosition('after')
                        ->link()
                        ->size(Size::Small)
                        ->color('gray')
                        ->tooltip($card->consoleLabel === null ? 'Open the provider console' : null)
                        ->url($card->consoleUrl, shouldOpenInNewTab: true),
                ]),
                Action::make("docs_{$card->key}")
                    ->label('Docs')
                    ->icon(Heroicon::OutlinedBookOpen)
                    ->link()
                    ->size(Size::Small)
                    ->color('gray')
                    ->tooltip('Read about these options in the docs')
                    ->url($this->docsUrl($card), shouldOpenInNewTab: true),
            ])
            ->schema([
                ...($card->fields)(),
                ...$this->callbackUrls($card),
                ...$this->advancedPart($card),
            ]);
    }

    /**
     * The card's part of the admin panel docs: the page's own docs page, at
     * the heading that has the card's title.
     */
    public function docsUrl(SettingsCard $card): string
    {
        return config('chatvia.docs_url').'/guide/admin-panel/'.static::docsPath().'#'.Str::slug($card->title);
    }

    /** The page's docs page, under the admin panel guide. */
    protected static function docsPath(): string
    {
        return 'settings/'.static::getSlug();
    }

    /**
     * The card's description, with its chips (what a service does, those in
     * use marked) below it, so they show while the card is closed too.
     */
    private function descriptionOf(SettingsCard $card): string|HtmlString
    {
        if (! $card->chips instanceof Closure) {
            return $card->description;
        }

        $chips = collect(($card->chips)())
            ->map(fn (bool $used, string $label): string => '<span class="cv-chip'.($used ? ' cv-chip--used' : '').'"'.($used ? ' title="In use"' : '').'>'.e($label).'</span>')
            ->join('');

        return new HtmlString(e($card->description).'<span class="cv-chips">'."{$chips}</span>");
    }

    /**
     * The card's technical fields in a closed part of their own. It has no
     * key: a key would change the paths of the fields inside it.
     *
     * @return list<Component>
     */
    private function advancedPart(SettingsCard $card): array
    {
        if (! $card->advancedFields instanceof Closure) {
            return [];
        }

        return [
            Section::make('Advanced')
                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                ->description(function () use ($card): string {
                    $changed = array_values(array_filter($this->namesOf(($card->advancedFields)()), $this->isSaved(...)));

                    return $changed === []
                        ? 'The defaults suit most installs.'
                        : 'Changed: '.collect($changed)->map(fn (string $property): string => $this->labelOf($card, $property))->join(', ', ' and ').'.';
                })
                ->compact()
                ->secondary()
                ->collapsible()
                ->collapsed()
                ->visible($card->advancedVisible ?? true)
                ->extraAttributes(['class' => 'cv-settings-advanced-part'])
                ->schema(($card->advancedFields)()),
        ];
    }

    /**
     * A card's status and its tone: a service is ready, incomplete or not
     * set up; a group of options says what is in use.
     *
     * @return array{0: string, 1: string}
     */
    private function statusLine(SettingsCard $card): array
    {
        if ($card->required === []) {
            return [$this->summaryOf($card), $card->tone instanceof Closure ? ($card->tone)() : 'gray'];
        }

        $status = $this->statusOf($card);

        return match ($status) {
            SettingStatus::Configured => [$card->summary instanceof Closure ? ($card->summary)() : 'Ready to use', 'success'],
            SettingStatus::Incomplete => [$this->summaryOf($card), 'warning'],
            SettingStatus::Missing => ['Not set up', 'gray'],
        };
    }

    /**
     * A card's link in the outline: its icon, and a status dot for a service.
     * A group of options gets a dot only when its summary has a color (a
     * change that waits), not for a plain grey summary.
     *
     * @return array{id: string, title: string, icon: string|Heroicon, status: string, tone: ?string, advanced: bool}
     */
    private function outlineItem(SettingsCard $card): array
    {
        [$status, $tone] = $this->statusLine($card);

        return [
            'id' => $this->sectionId($card),
            'title' => $card->title,
            'icon' => $card->icon,
            'status' => $status,
            'tone' => $card->required !== [] || $tone !== 'gray' ? $tone : null,
            'advanced' => $card->advanced,
        ];
    }

    /**
     * @return list<string>
     */
    protected function propertiesOf(SettingsCard $card): array
    {
        if ($card->properties !== null) {
            return $card->properties;
        }

        $configKeys = static::$settings::configKeys();

        return array_values(array_filter(
            $this->namesOf($this->fieldsOf($card)),
            fn (string $name): bool => array_key_exists($name, $configKeys),
        ));
    }

    /**
     * The card's top-level components: its fields and those of its
     * "Advanced" part.
     *
     * @return list<Component>
     */
    private function fieldsOf(SettingsCard $card): array
    {
        return [
            ...($card->fields)(),
            ...($card->advancedFields instanceof Closure ? ($card->advancedFields)() : []),
        ];
    }

    /**
     * @param  list<Component>  $components
     * @return list<string>
     */
    private function namesOf(array $components): array
    {
        return array_values(array_filter(array_map(
            fn (Component $field): ?string => $field instanceof Field ? $field->getName() : null,
            $components,
        )));
    }

    protected function statusOf(SettingsCard $card): SettingStatus
    {
        if (! array_any($this->propertiesOf($card), $this->isSaved(...))) {
            return SettingStatus::Missing;
        }

        return array_all($card->required, $this->isSaved(...)) ? SettingStatus::Configured : SettingStatus::Incomplete;
    }

    private function summaryOf(SettingsCard $card): string
    {
        if ($card->summary instanceof Closure) {
            return ($card->summary)();
        }

        if ($card->required !== []) {
            return match ($this->statusOf($card)) {
                SettingStatus::Configured => 'Ready to use.',
                SettingStatus::Incomplete => 'Missing: '.collect($card->required)
                    ->reject($this->isSaved(...))
                    ->map(fn (string $property): string => $this->labelOf($card, $property))
                    ->join(', ', ' and ').'.',
                SettingStatus::Missing => 'Not set up yet.',
            };
        }

        $changed = array_values(array_filter($this->propertiesOf($card), $this->isSaved(...)));

        return $changed === []
            ? 'Using the defaults.'
            : 'Changed: '.collect($changed)->map(fn (string $property): string => $this->labelOf($card, $property))->join(', ', ' and ').'.';
    }

    private function labelOf(SettingsCard $card, string $property): string
    {
        foreach ($this->fieldsOf($card) as $field) {
            if ($field instanceof Field && $field->getName() === $property) {
                return (string) $field->getLabel();
            }
        }

        return Str::headline($property);
    }

    /**
     * The panel's state: the saved values, never a secret. A toggle (and any
     * property {@see showsValueInUse()}) shows the value in use.
     *
     * @param  list<string>  $properties
     * @return array<string, mixed>
     */
    private function formValues(array $properties): array
    {
        $state = [];

        foreach ($properties as $property) {
            $value = $this->settings()->{$property};

            $state[$property] = match (true) {
                static::$settings::isSecret($property) => null,
                static::$settings::typeOf($property) === 'bool' => $value ?? (bool) $this->defaultValue($property),
                $this->showsValueInUse($property) => $value ?? $this->defaultValue($property),
                default => $value,
            };
        }

        return $state;
    }

    /**
     * The panel's input as the values to save.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function valuesToSave(array $data): array
    {
        $settings = $this->settings();
        $values = [];

        foreach ($data as $property => $value) {
            // Fields that only help the panel (a filter, a preview) are not
            // settings.
            if (! array_key_exists($property, static::$settings::configKeys())) {
                continue;
            }

            $value = static::$settings::cast($property, $value);

            // A blank secret is "unchanged": keep the saved one.
            if ($value === null && static::$settings::isSecret($property)) {
                continue;
            }

            // Still the default and never saved: stay unsaved, so a later
            // change of the default applies.
            if ($value !== null && $settings->{$property} === null && $value === static::$settings::cast($property, $this->defaultValue($property))) {
                $value = null;
            }

            $values[$property] = $value;
        }

        return $values;
    }

    /**
     * @return list<Component>
     */
    private function callbackUrls(SettingsCard $card): array
    {
        // Only the routes this install has (a channel's routes may be off).
        $routes = array_filter($card->callbackRoutes, fn (string|array $route): bool => Route::has(is_array($route) ? $route[0] : $route));

        if ($routes === []) {
            return [];
        }

        return [
            // Built on APP_URL, not on this request: behind Docker or a proxy
            // the request's host can be localhost or a container name.
            Section::make('Register these URLs with the provider')
                ->icon(Heroicon::OutlinedLink)
                ->description(PublicUrl::isInternal()
                    ? 'These URLs use this install\'s address (APP_URL), '.PublicUrl::base().', which the provider cannot reach. Set APP_URL to the public address first.'
                    : null)
                ->compact()
                ->secondary()
                ->schema(array_map(
                    fn (string $label, string|array $route): TextEntry => TextEntry::make('callback_'.Str::snake($label))
                        ->label($label)
                        ->state(is_array($route) ? PublicUrl::route($route[0], $route[1]) : PublicUrl::route($route))
                        ->fontFamily(FontFamily::Mono)
                        ->copyable()
                        ->copyMessage('Copied'),
                    array_keys($routes),
                    $routes,
                )),
        ];
    }

    // --- Fields --------------------------------------------------------------

    /**
     * A write-only input for a key, secret or password.
     */
    protected function secret(string $property, string $label, ?string $help = null, bool $required = true): TextInput
    {
        return $this->withHelp(TextInput::make($property)
            ->label($label)
            ->password()
            ->revealable()
            ->autocomplete('new-password')
            ->rules(['nullable', 'string', 'max:2000'])
            ->placeholder(fn (): ?string => $this->isSaved($property) ? '•••••••• saved, leave blank to keep' : null)
            ->hint(fn (): ?string => $this->hint($property, $required))
            ->hintIcon(fn (): ?Heroicon => $this->isSaved($property) ? Heroicon::OutlinedLockClosed : null)
            ->hintColor(fn (): string => $this->isSaved($property) ? 'success' : 'gray')
            ->suffixAction($this->forgetAction($property, $label)), $help);
    }

    protected function text(string $property, string $label, ?string $help = null, bool $required = false): TextInput
    {
        return $this->withHelp(TextInput::make($property)
            ->label($label)
            ->rules(['nullable', 'string', 'max:2000'])
            ->placeholder(fn (): ?string => $this->defaultText($property))
            ->hint(fn (): ?string => $required && ! $this->isSaved($property) ? 'Required' : null)
            ->hintColor('gray'), $help);
    }

    /**
     * Several lines of code, such as HTML, kept as typed. Blank uses the
     * default.
     */
    protected function code(string $property, string $label, ?string $help = null, ?string $placeholder = null): Textarea
    {
        return $this->withHelp(Textarea::make($property)
            ->label($label)
            ->rows(6)
            ->autosize()
            ->rules(['nullable', 'string', 'max:20000'])
            ->placeholder($placeholder)
            ->extraInputAttributes(['class' => 'font-mono text-sm', 'spellcheck' => 'false']), $help);
    }

    protected function url(string $property, string $label, ?string $help = null, bool $required = false): TextInput
    {
        return $this->text($property, $label, $help, $required)->url();
    }

    /**
     * A full address, or a path on this server such as "/shakomako/embed.js"
     * for a file this install serves itself. The code reads it through url().
     */
    protected function urlOrPath(string $property, string $label, ?string $help = null): TextInput
    {
        return $this->text($property, $label, $help)
            ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                $isPath = filled($value) && Str::isMatch('#^/(?!/)\S*$#', $value);
                $isUrl = filled($value) && Str::isMatch('#^https?://#i', $value) && filter_var($value, FILTER_VALIDATE_URL) !== false;

                if (! $isPath && ! $isUrl) {
                    $fail('Enter a path on this server that starts with "/", or a full address that starts with "https://".');
                }
            });
    }

    protected function email(string $property, string $label, ?string $help = null): TextInput
    {
        return $this->text($property, $label, $help)->email();
    }

    protected function integer(string $property, string $label, ?string $help = null, int $min = 0, ?int $max = null): TextInput
    {
        return $this->withHelp(TextInput::make($property)
            ->label($label)
            ->integer()
            ->minValue($min)
            ->maxValue($max)
            ->placeholder(fn (): ?string => $this->defaultText($property)), $help);
    }

    protected function decimal(string $property, string $label, ?string $help = null, float $min = 0, float $max = 1, float $step = 0.01): TextInput
    {
        return $this->withHelp(TextInput::make($property)
            ->label($label)
            ->numeric()
            ->minValue($min)
            ->maxValue($max)
            ->step($step)
            ->placeholder(fn (): ?string => $this->defaultText($property)), $help);
    }

    protected function toggle(string $property, string $label, ?string $help = null): Toggle
    {
        return $this->withHelp(Toggle::make($property)->label($label), $help);
    }

    /**
     * A "?" right after the field's label, with the help as its tooltip, in
     * place of a line of help under the field. No help, no "?".
     *
     * @template T of Field
     *
     * @param  T  $field
     * @return T
     */
    protected function withHelp(Field $field, ?string $help): Field
    {
        if (blank($help)) {
            return $field;
        }

        return $field->afterLabel(Schema::start([
            Icon::make(Heroicon::OutlinedQuestionMarkCircle)
                ->color('gray')
                ->tooltip($help),
        ]));
    }

    /**
     * @param  array<string, string>  $options  Label by stored value.
     */
    protected function choice(string $property, string $label, array $options, ?string $help = null): Select
    {
        return $this->withHelp(Select::make($property)
            ->label($label)
            ->options($options)
            ->in(array_keys($options))
            ->placeholder(function () use ($property, $options): string {
                $default = $this->defaultValue($property);

                return 'Default: '.($options[(string) $default] ?? (filled($default) ? (string) $default : 'none'));
            }), $help);
    }

    /** "On", "Off", or the value, for a summary line. */
    protected function shown(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'on' : 'off',
            is_array($value) => Arr::join($value, ', '),
            blank($value) => 'none',
            default => (string) $value,
        };
    }

    private function hint(string $property, bool $required): ?string
    {
        return match (true) {
            $this->isSaved($property) => 'Saved',
            $required => 'Required',
            default => null,
        };
    }

    private function forgetAction(string $property, string $label): Action
    {
        return Action::make("forget_{$property}")
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->tooltip('Remove the saved value')
            ->visible(fn (): bool => $this->isSaved($property))
            ->requiresConfirmation()
            ->modalHeading("Remove the saved {$label}?")
            ->modalDescription(fn (): string => $this->forgetDescription($property, $label))
            ->modalSubmitActionLabel('Remove')
            ->action(function () use ($property, $label): void {
                $settings = $this->settings();
                $previous = $settings->{$property};
                $settings->{$property} = null;
                $settings->save();

                $this->forgotten($property, $previous);

                Notification::make()->success()->title("{$label} removed")->send();
            });
    }

    /** What removing a saved secret stops, shown before it is removed. */
    protected function forgetDescription(string $property, string $label): string
    {
        return 'The features that use it stop working until you add a new value.';
    }

    /** Runs after a saved secret was removed, for a page that must tell a service. */
    protected function forgotten(string $property, mixed $previous): void {}

    private function defaultText(string $property): ?string
    {
        $default = $this->defaultValue($property);

        return match (true) {
            is_bool($default) => $default ? 'On' : 'Off',
            is_scalar($default) && $default !== '' => (string) $default,
            default => null,
        };
    }
}
