<?php

declare(strict_types=1);

namespace App\Admin\Settings\Actions;

use App\Admin\Settings\AiSettings;
use Domain\Conversation\Enums\GeneratedMediaKind;
use Domain\Llm\Data\Media\MediaModelData;
use Domain\Llm\Enums\AiProvider;
use InvalidArgumentException;

/**
 * Adds models of one service to the image or video models (Settings → AI
 * models), after the ones already listed; a model listed already stays
 * where it is. The first model ever added becomes the default.
 */
final readonly class AddMediaModelsAction
{
    public function __construct(
        private AiSettings $ai,
    ) {}

    /**
     * @param  list<string>  $models  the service's model ids
     */
    public function execute(GeneratedMediaKind $kind, AiProvider $service, array $models): void
    {
        [$property, $default] = match ($kind) {
            GeneratedMediaKind::Image => ['image_models', 'default_image_model'],
            GeneratedMediaKind::Video => ['video_models', 'default_video_model'],
            GeneratedMediaKind::Audio => throw new InvalidArgumentException('Audio files have no list of models.'),
        };
        $listed = collect((array) $this->ai->{$property})
            ->merge(collect($models)->map(fn (string $model): string => MediaModelData::keyOf($service, $model)))
            ->unique()
            ->values();

        $this->ai->{$property} = $listed->all();
        $this->ai->{$default} = filled($this->ai->{$default}) ? $this->ai->{$default} : $listed->first();
        $this->ai->save();
    }
}
