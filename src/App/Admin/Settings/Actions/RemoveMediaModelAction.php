<?php

declare(strict_types=1);

namespace App\Admin\Settings\Actions;

use App\Admin\Settings\AiSettings;
use Domain\Conversation\Enums\GeneratedMediaKind;
use InvalidArgumentException;

/**
 * Takes a model off the image or video models (Settings → AI models). When
 * it was the default, the first model left becomes the default; with none
 * left, agents make no pictures (or videos) until one is added. Items still
 * being made keep the service they started on.
 */
final readonly class RemoveMediaModelAction
{
    public function __construct(
        private AiSettings $ai,
    ) {}

    public function execute(GeneratedMediaKind $kind, string $key): void
    {
        [$property, $default] = match ($kind) {
            GeneratedMediaKind::Image => ['image_models', 'default_image_model'],
            GeneratedMediaKind::Video => ['video_models', 'default_video_model'],
            GeneratedMediaKind::Audio => throw new InvalidArgumentException('Audio files have no list of models.'),
        };
        $listed = collect((array) $this->ai->{$property})->reject(fn (mixed $entry): bool => $entry === $key)->values();

        $this->ai->{$property} = $listed->all();

        if ($this->ai->{$default} === $key) {
            $this->ai->{$default} = $listed->first();
        }

        $this->ai->save();
    }
}
