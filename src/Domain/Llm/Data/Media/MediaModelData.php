<?php

declare(strict_types=1);

namespace Domain\Llm\Data\Media;

use Domain\Llm\Enums\AiProvider;
use Spatie\LaravelData\Data;

/**
 * One image or video model the admin listed (Settings → AI models): the
 * service that runs it, the service's model id, its label for the admin and
 * its name for chatters. Its key, `{service}:{model}`, is what settings,
 * the hosted chat's model menu and each chat's pick store.
 */
class MediaModelData extends Data
{
    public function __construct(
        public AiProvider $service,
        public string $model,
        public string $label,
        public string $name,
    ) {}

    public static function keyOf(AiProvider $service, string $model): string
    {
        return "{$service->value}:{$model}";
    }

    public function key(): string
    {
        return self::keyOf($this->service, $this->model);
    }
}
