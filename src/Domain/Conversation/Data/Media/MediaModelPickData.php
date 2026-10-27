<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Media;

use Domain\Agent\Services\OfferedMediaModels;
use Illuminate\Support\Arr;
use Spatie\LaravelData\Data;

/**
 * The image and video model a chatter picked in the hosted chat's header,
 * sent with each message. Not checked yet: what the agent offers decides
 * when a picture or video is asked for ({@see OfferedMediaModels}).
 */
class MediaModelPickData extends Data
{
    public function __construct(
        public ?string $image = null,
        public ?string $video = null,
    ) {}

    /**
     * @return array{image?: string, video?: string}
     */
    public function toStored(): array
    {
        return Arr::where(['image' => $this->image, 'video' => $this->video], filled(...));
    }
}
