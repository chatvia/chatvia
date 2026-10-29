<?php

declare(strict_types=1);

namespace App\Admin\Clusters\Settings\Components;

use App\Admin\Clusters\Settings\Pages\ManageAiModelSettings;
use App\Admin\Forms\ModelPicker;
use App\Admin\Settings\Actions\AddMediaModelsAction;
use App\Admin\Settings\Actions\RemoveMediaModelAction;
use App\Admin\Settings\AiProviderCheck;
use App\Admin\Settings\Data\ConnectionCheckData;
use App\Admin\Settings\ModelLogo;
use App\Admin\Settings\OptionLabel;
use Domain\Conversation\Enums\GeneratedMediaKind;
use Domain\Llm\Data\Media\MediaModelData;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Services\MediaModelCatalog;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Livewire\Component;

/**
 * The image or video models on Settings → AI models, as a table like the
 * Chat card's: a model of any service that makes them can be added (several
 * services at once), and removed. The default, picked under the table on
 * the page, is tagged. Its own Livewire component, since the page's one
 * table is the Chat card's. Each change is saved at once and tells the page
 * ({@see ManageAiModelSettings}), which shows the new default choices.
 */
class MediaModelsTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    /** Notes on a service, shown as it is picked to add its models: title => text. */
    private const array NOTES = [
        'image' => [
            'openai' => ['A small text model hands OpenAI the prompt', 'OpenAI draws through its Responses API, so each picture also costs a few text tokens. OpenAI does not keep the pictures.'],
            'openrouter' => ['Prices differ a lot between OpenRouter models', 'Check a model\'s price on OpenRouter before you add it. Listed with a few good picks first, then every model on OpenRouter whose output includes images.'],
            'google' => ['Google marks every picture', 'Each picture carries Google\'s SynthID watermark, which people cannot see but tools can find.'],
            'fal' => ['Prices differ between fal.ai models', 'Check a model\'s price on fal.ai before you add it. FLUX.2 dev and Qwen-Image are open models.'],
        ],
        'video' => [
            'google' => ['Google moved on from Veo', 'Google shut down the Veo 3.1 preview models on the Gemini API. Gemini Omni Flash is listed first, and Veo 3 is listed while Google still serves it. For Veo 3.1, add it from ElevenLabs, Runway or fal.ai. Each video carries Google\'s SynthID watermark.'],
            'elevenlabs' => ['ElevenLabs needs a Pro plan', 'Making videos through its API needs the Pro plan or above. Its Seedance models work only after ElevenLabs approves your account for them.'],
            'kling' => ['Prices differ a lot between models', 'A video can cost from a few cents to a few dollars. Check the model\'s price before you add it.'],
            'byteplus' => ['Seedance needs a resource pack', 'Buy a prepaid resource pack for each model in the BytePlus console first, or every video fails. A pack works for one model only.'],
            'runway' => ['Runway bills in credits', 'Each second of video costs credits from your Runway organization. Add credits in the Runway developer portal.'],
            'fal' => ['Prices differ a lot between models', 'A video can cost from a few cents to a few dollars. Check the model\'s price before you add it.'],
        ],
    ];

    /** `image` or `video`. */
    public string $kind;

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => collect($this->listed())
                ->mapWithKeys(fn (MediaModelData $model): array => [$model->key() => [
                    'key' => $model->key(),
                    'label' => $model->label,
                    'model' => $model->model,
                    'service' => $model->service->label(),
                    'service_value' => $model->service->value,
                    'connected' => $model->service->isConnected(),
                ]])
                ->all())
            ->columns([
                TextColumn::make('label')
                    ->label('Model')
                    ->formatStateUsing(fn (array $record): HtmlString => new HtmlString(OptionLabel::provider($record['label'], AiProvider::from($record['service_value']))))
                    ->suffix(fn (array $record): string => $record['key'] === $this->defaultModel()?->key() ? ' · default' : '')
                    ->description(fn (array $record): string => $record['model']),
                TextColumn::make('service')
                    ->label('Service')
                    ->description(fn (array $record): ?string => $record['connected'] ? null : 'Not connected')
                    ->color(fn (array $record): string => $record['connected'] ? 'gray' : 'warning'),
            ])
            ->headerActions([$this->addAction()])
            ->recordActions([$this->removeAction()])
            ->emptyStateHeading($this->kind() === GeneratedMediaKind::Video ? 'No video models yet' : 'No image models yet')
            ->emptyStateDescription('Connect a service in AI providers, then add its models here.')
            ->emptyStateIcon($this->kind() === GeneratedMediaKind::Video ? Heroicon::OutlinedFilm : Heroicon::OutlinedPhoto)
            ->paginated(false);
    }

    public function render(): View
    {
        return view('admin.settings.media-models-table');
    }

    /**
     * Adds models of one service. Every service that makes this kind is
     * listed, the ones without a key greyed out, and the first connected one
     * is picked as the modal opens. A service new to the list is checked
     * with a real call first, and refused when the call fails.
     */
    private function addAction(): Action
    {
        return Action::make('addMediaModel')
            ->label('Add a model')
            ->icon(Heroicon::OutlinedPlus)
            ->modalHeading('Add a model')
            ->modalWidth(Width::TwoExtraLarge)
            ->schema([
                Select::make('service')
                    ->label('Service')
                    ->options(fn (): array => collect($this->services())
                        ->sortBy(fn (AiProvider $service): int => $service->isConnected() ? 0 : 1)
                        ->mapWithKeys(fn (AiProvider $service): array => [$service->value => OptionLabel::provider($service->label().($service->isConnected() ? '' : ' (no key yet)'), $service)])
                        ->all())
                    ->allowHtml()
                    ->disableOptionWhen(fn (string $value): bool => ! AiProvider::from($value)->isConnected())
                    ->default(fn (): ?string => collect($this->services())->first(fn (AiProvider $service): bool => $service->isConnected())?->value)
                    ->required()
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(fn (Set $set): mixed => $set('models', [])),
                ...collect(self::NOTES[$this->kind] ?? [])->map(fn (array $note, string $service): Callout => Callout::make($note[0])
                    ->description($note[1])
                    ->icon(Heroicon::OutlinedInformationCircle)
                    ->info()
                    ->visible(fn (Get $get): bool => $get('service') === $service))->values()->all(),
                ModelPicker::make('models')
                    ->options(fn (Get $get): array => $this->offeredBy(AiProvider::tryFrom((string) $get('service'))))
                    ->disableOptionWhen(fn (string $value, Get $get): bool => collect($this->listed())->contains(fn (MediaModelData $model): bool => $model->key() === MediaModelData::keyOf(AiProvider::from((string) $get('service')), $value)))
                    ->required()
                    ->validationMessages(['required' => 'Tick at least one model.'])
                    ->visible(fn (Get $get): bool => filled($get('service'))),
            ])
            ->modalSubmitActionLabel('Add')
            ->action(function (array $data, Action $action): void {
                $service = AiProvider::from((string) $data['service']);
                $check = $this->isListed($service) ? null : app(AiProviderCheck::class)->check($service);

                if ($check instanceof ConnectionCheckData && ! $check->connected) {
                    Notification::make()->danger()->title("{$service->label()} did not answer")->body($check->message)->send();
                    $action->halt();
                }

                if ($check?->warning !== null) {
                    Notification::make()->warning()->title($check->warning)->persistent()->send();
                }

                app(AddMediaModelsAction::class)->execute($this->kind(), $service, array_values((array) $data['models']));
                $this->dispatch('media-models-changed');

                Notification::make()->success()->title(count((array) $data['models']) === 1 ? 'Model added' : 'Models added')->send();
            });
    }

    private function removeAction(): Action
    {
        return Action::make('removeMediaModel')
            ->label('Remove')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (array $record): string => "Remove {$record['label']}?")
            ->modalDescription(fn (array $record): string => $this->removalNote($record['key']))
            ->modalSubmitActionLabel('Remove')
            ->action(function (array $record): void {
                app(RemoveMediaModelAction::class)->execute($this->kind(), $record['key']);
                $this->dispatch('media-models-changed');

                Notification::make()->success()->title("{$record['label']} removed")->send();
            });
    }

    private function removalNote(string $key): string
    {
        $others = collect($this->listed())->reject(fn (MediaModelData $model): bool => $model->key() === $key);
        $things = $this->kind() === GeneratedMediaKind::Video ? 'videos' : 'pictures';

        return match (true) {
            $others->isEmpty() => "Agents can no longer make {$things} until you add a model.",
            $this->defaultModel()?->key() === $key => "{$others->first()->label} becomes the default. Chatters can no longer pick this model.",
            default => 'Chatters can no longer pick it. You can add it again later.',
        };
    }

    /**
     * The models a service offers, with OpenRouter's maker logos.
     *
     * @return array<string, string>
     */
    private function offeredBy(?AiProvider $service): array
    {
        if (! $service instanceof AiProvider) {
            return [];
        }

        $catalog = app(MediaModelCatalog::class);
        $models = $this->kind() === GeneratedMediaKind::Video ? $catalog->videoModelsOf($service) : $catalog->imageModelsOf($service);

        return $service === AiProvider::OpenRouter ? ModelLogo::labels($models, LlmProviderKind::OpenRouter) : $models;
    }

    /**
     * @return list<AiProvider>
     */
    private function services(): array
    {
        $catalog = app(MediaModelCatalog::class);

        return $this->kind() === GeneratedMediaKind::Video ? $catalog->videoServices() : $catalog->imageServices();
    }

    /**
     * @return list<MediaModelData>
     */
    private function listed(): array
    {
        $catalog = app(MediaModelCatalog::class);

        return $this->kind() === GeneratedMediaKind::Video ? $catalog->videoModels() : $catalog->imageModels();
    }

    private function defaultModel(): ?MediaModelData
    {
        $catalog = app(MediaModelCatalog::class);

        return $this->kind() === GeneratedMediaKind::Video ? $catalog->defaultVideo() : $catalog->defaultImage();
    }

    private function isListed(AiProvider $service): bool
    {
        return collect($this->listed())->contains(fn (MediaModelData $model): bool => $model->service === $service);
    }

    private function kind(): GeneratedMediaKind
    {
        return GeneratedMediaKind::from($this->kind);
    }
}
