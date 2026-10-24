<?php

namespace Domain\Llm\Operations\Media;

use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Enums\LlmTask;
use Domain\Llm\Services\LlmChat;
use Domain\Llm\Services\TaskModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Describes an image in words with the Image descriptions model (LlmTask::Images),
 * so images inside documents can be embedded and found by search. The model
 * answers `no_info` for images that carry no information (logos, decoration);
 * those get no description.
 */
class Vision
{
    /** The model's answer for an image that is not worth describing. */
    private const string NO_INFO = 'no_info';

    public function __construct(
        private readonly LlmChat $chat,
        private readonly TaskModels $models,
    ) {}

    /**
     * @param  string  $image  a data URI, a URL, or a local file path
     * @return string|null null when the image carries no information
     */
    public function describeImage(string $image): ?string
    {
        if (File::exists($image)) {
            $image = Image::decodePath($image)->encode(new JpegEncoder)->toDataUri()->toString();
        }

        $description = $this->chat->send(new ChatRequestData(
            model: $this->models->model(LlmTask::Images),
            messages: [
                ChatMessageData::userWithAttachment(prompt('image/describe'), [
                    'type' => 'image_url',
                    'image_url' => ['url' => $image],
                ]),
            ],
            tag: AiCallTag::ImageDescription,
        ))->content;

        return $description === '' || Str::contains($description, self::NO_INFO) ? null : $description;
    }

    /**
     * Describe an image given as raw bytes (any format Intervention can read).
     */
    public function describeImageBytes(string $bytes): ?string
    {
        return $this->describeImage(Image::decode($bytes)->encode(new JpegEncoder)->toDataUri()->toString());
    }
}
