<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Pages;

use App\Admin\Clusters\Settings\AdminSettingsPage;
use App\Admin\Clusters\Settings\SettingsCard;
use App\Admin\Settings\Data\PromptData;
use App\Admin\Settings\PromptCatalog;
use App\Admin\Settings\PromptSettings;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Text;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Support\Prompt\PromptTemplate;

/**
 * The prompts the app gives the AI models, one folded card each: open one to
 * read and change it, or restore its default (its file in resources/prompts).
 * Only edits are saved; a prompt that still equals its file keeps following
 * the file. An edit must keep to the safe part of Blade and use only the
 * variables the prompt is given ({@see PromptTemplate}).
 */
class ManagePromptSettings extends AdminSettingsPage
{
    /** The icon of each group of prompts. */
    private const array GROUP_ICONS = [
        'Chat' => Heroicon::OutlinedChatBubbleLeftRight,
        'Search' => Heroicon::OutlinedMagnifyingGlass,
        'Training' => Heroicon::OutlinedAcademicCap,
        'Conversations' => Heroicon::OutlinedChartBar,
        'Safety and quality' => Heroicon::OutlinedShieldCheck,
        'Setup helpers' => Heroicon::OutlinedSparkles,
    ];

    #[\Override]
    protected static string $settings = PromptSettings::class;

    #[\Override]
    protected static ?string $title = 'Prompts';

    #[\Override]
    protected static ?string $slug = 'prompts';

    #[\Override]
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;

    #[\Override]
    protected static ?int $navigationSort = 15;

    public static function description(): string
    {
        return 'The instructions the app gives the AI models. Change one, or restore its default.';
    }

    public static function overviewGroup(): string
    {
        return 'AI and knowledge';
    }

    #[\Override]
    public function mount(): void
    {
        $this->form->fill(['prompts' => array_combine(
            array_map(fn (PromptData $prompt): string => $this->fieldKey($prompt->name), $this->catalog()->all()),
            array_map($this->textInUse(...), $this->catalog()->all()),
        )]);
    }

    /**
     * Saves the prompts that differ from their file; a prompt left empty or
     * equal to its file follows the file again.
     */
    #[\Override]
    public function save(): void
    {
        $texts = (array) ($this->form->getState()['prompts'] ?? []);
        $edits = [];

        foreach ($this->catalog()->all() as $prompt) {
            $text = (string) ($texts[$this->fieldKey($prompt->name)] ?? '');

            if (! $this->isDefault($text, $prompt)) {
                $edits[$prompt->name] = $this->normalized($text);
            }
        }

        $this->store($edits);
        $this->mount();

        Notification::make()->success()->title('Prompts saved')->send();
    }

    #[\Override]
    public function overviewStatus(): array
    {
        $edited = count($this->edits());

        return $edited === 0 ? ['Using the defaults', 'gray'] : [$edited.' '.Str::plural('prompt', $edited).' changed', 'primary'];
    }

    protected function cards(): array
    {
        return array_map($this->card(...), $this->catalog()->all());
    }

    private function card(PromptData $prompt): SettingsCard
    {
        $key = $this->fieldKey($prompt->name);
        $variables = $this->catalog()->variables($prompt->name);

        return SettingsCard::make($key, $prompt->label, "{$prompt->group}: {$prompt->description}", self::GROUP_ICONS[$prompt->group] ?? Heroicon::OutlinedCommandLine, fn (): array => [
            Text::make($variables === []
                ? 'This prompt is given no variables.'
                : 'Variables: '.implode(', ', array_map(fn (string $name): string => '{!! $'.$name.' !!}', $variables)))
                ->size(TextSize::Small)
                ->color('gray'),
            Textarea::make("prompts.{$key}")
                ->hiddenLabel()
                ->autosize()
                ->rows(8)
                ->extraInputAttributes(['class' => 'font-mono text-sm', 'spellcheck' => 'false'])
                ->helperText('Plain text with {!! $variable !!}, @if, @foreach and @unless, as in the file. Leave it empty to use the default.')
                ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail) use ($variables): void {
                    $problems = PromptTemplate::problems((string) $value, $variables);

                    if ($problems !== []) {
                        $fail(implode(' ', $problems));
                    }
                }),
            Actions::make([
                Action::make("restore_{$key}")
                    ->label('Restore default')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('gray')
                    ->link()
                    ->requiresConfirmation()
                    ->modalHeading("Restore the default {$prompt->label} prompt?")
                    ->modalDescription('Your changes to this prompt are removed, and the app uses its default text again.')
                    ->visible(fn (): bool => array_key_exists($prompt->name, $this->edits()))
                    ->action(fn () => $this->restore($prompt)),
            ])->key("restore-{$key}"),
        ])
            ->collapsed()
            // One explanation for every prompt (resources/help/settings/prompts/prompt.md).
            ->helpFile('prompt')
            ->summary(fn (): string => array_key_exists($prompt->name, $this->edits()) ? 'Changed' : 'Default');
    }

    /** Removes one edit at once, and shows the default in its field; other unsaved changes stay. */
    private function restore(PromptData $prompt): void
    {
        $edits = $this->edits();
        unset($edits[$prompt->name]);
        $this->store($edits);

        $this->data['prompts'][$this->fieldKey($prompt->name)] = $prompt->default;

        Notification::make()->success()->title("The default {$prompt->label} prompt is back")->send();
    }

    /**
     * @param  array<string, string>  $edits
     */
    private function store(array $edits): void
    {
        $settings = app(PromptSettings::class);
        $settings->overrides = $edits === [] ? null : $edits;
        $settings->save();
    }

    /**
     * The saved edits of the prompts that still exist.
     *
     * @return array<string, string>
     */
    private function edits(): array
    {
        return array_filter(
            (array) app(PromptSettings::class)->overrides,
            fn (mixed $text, string|int $name): bool => is_string($text) && $this->catalog()->has((string) $name),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    private function textInUse(PromptData $prompt): string
    {
        return $this->edits()[$prompt->name] ?? $prompt->default;
    }

    private function isDefault(string $text, PromptData $prompt): bool
    {
        return trim($text) === '' || rtrim($this->normalized($text)) === rtrim($this->normalized($prompt->default));
    }

    /** Line endings as in the files, so a browser's \r\n is not a change. */
    private function normalized(string $text): string
    {
        return str_replace("\r\n", "\n", $text);
    }

    /** A form key for a prompt name: "knowledge/rewrite-query" → "knowledge_rewrite_query". */
    private function fieldKey(string $name): string
    {
        return (string) preg_replace('/[^a-z0-9]+/', '_', strtolower($name));
    }

    private function catalog(): PromptCatalog
    {
        return app(PromptCatalog::class);
    }
}
