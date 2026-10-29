<?php

declare(strict_types=1);

namespace Domain\Llm\Services;

use Closure;
use Domain\Llm\Data\Media\MediaModelData;
use Domain\Llm\Enums\AiProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The image and video models each service offers, with a label for the
 * admin (Settings → AI models) and a short name for chatters who pick one in
 * the hosted chat, and the models the admin listed: from one service or
 * several, as `{service}:{model}` keys ({@see MediaModelData}), one of them
 * the default. Each generator's `MODELS` lists the same ids. OpenRouter's
 * image models come from {@see OpenRouterImageModels}, as they change often,
 * so any id the admin added there stays listed. A kind with no models listed
 * is off.
 */
final readonly class MediaModelCatalog
{
    /** The image models per service, the default first. */
    public const array IMAGE_MODELS = [
        'openai' => [
            'gpt-image-2.5-flare' => 'GPT Image 2.5 Flare (fast)',
            'gpt-image-2.5-sunburst' => 'GPT Image 2.5 Sunburst (best detail, costs more)',
            'gpt-image-2' => 'GPT Image 2',
            'gpt-image-1.5' => 'GPT Image 1.5',
            'gpt-image-1-mini' => 'GPT Image 1 mini (cheapest)',
        ],
        'google' => [
            'gemini-nano-banana-2.1' => 'Nano Banana 2.1 (fast, good pictures)',
            'gemini-3.1-flash-lite-image' => 'Nano Banana 2 Lite (fastest, cheapest)',
            'gemini-3-pro-image' => 'Nano Banana Pro (best pictures, costs more)',
            'gemini-3.1-flash-image' => 'Nano Banana 2',
        ],
        // Open ones first; FalImageGenerator::MODELS says how each takes the shape.
        'fal' => [
            'fal-ai/flux-2' => 'FLUX.2 dev (open model)',
            'fal-ai/qwen-image' => 'Qwen-Image (open model, good with text in pictures)',
            'fal-ai/flux-2-pro' => 'FLUX.2 pro (costs more)',
            'fal-ai/nano-banana-2' => 'Nano Banana 2 (Google)',
            'fal-ai/nano-banana-pro' => 'Nano Banana Pro (Google, costs more)',
            'fal-ai/bytedance/seedream/v4.5/text-to-image' => 'Seedream 4.5 (ByteDance)',
            'fal-ai/recraft/v3/text-to-image' => 'Recraft V3 (logos and designs)',
        ],
    ];

    /** The video models per service, the default first. */
    public const array VIDEO_MODELS = [
        'google' => [
            'gemini-omni-1.1-flash' => 'Gemini Omni Flash (with sound, 3 to 10 seconds)',
            'veo-3.0-fast-generate-001' => 'Veo 3 Fast (deprecated by Google, 8 seconds)',
            'veo-3.0-generate-001' => 'Veo 3 (deprecated by Google, 8 seconds, costs more)',
        ],
        'elevenlabs' => [
            'veo-3.1-fast-generate-001' => 'Google Veo 3.1 Fast (with sound, 4 to 8 seconds)',
            'veo-3.1-generate-001' => 'Google Veo 3.1 (best, costs more)',
            'bytedance-seedance-v2-fast' => 'Seedance 2.0 Fast (720p, needs approval)',
            'bytedance-seedance-v2' => 'Seedance 2.0 (needs approval)',
            'bytedance-seedance-v2.5' => 'Seedance 2.5 (needs approval)',
        ],
        'kling' => [
            'kling-3.0-turbo' => 'Kling 3.0 Turbo (fast, 3 to 15 seconds)',
            'kling-4.0' => 'Kling 4.0 (best, up to 30 seconds, costs more)',
        ],
        'byteplus' => [
            'dreamina-seedance-2-0-fast-260128' => 'Seedance 2.0 Fast (720p, cheaper)',
            'dreamina-seedance-2-0-260128' => 'Seedance 2.0 (up to 1080p)',
        ],
        'runway' => [
            'gen4.5' => 'Gen-4.5 (2 to 10 seconds, 720p)',
            'veo3.1_fast' => 'Google Veo 3.1 Fast (with sound)',
            'veo3.1' => 'Google Veo 3.1 (with sound, costs more)',
            'kling3.0_pro' => 'Kling 3.0 Pro (1080p)',
            'seedance2' => 'Seedance 2.0',
        ],
        'fal' => [
            'fal-ai/wan/v2.2-a14b/text-to-video' => 'Wan 2.2 (open model, no sound, cheapest)',
            'fal-ai/veo3.1/fast' => 'Google Veo 3.1 Fast (with sound)',
            'fal-ai/veo3.1/lite' => 'Google Veo 3.1 Lite (with sound, cheaper)',
            'fal-ai/veo3.1' => 'Google Veo 3.1 (with sound, costs more)',
            'fal-ai/kling-video/v3/pro/text-to-video' => 'Kling 3.0 Pro (with sound)',
        ],
    ];

    public function __construct(
        private OpenRouterImageModels $openRouter,
    ) {}

    /**
     * The image models the admin listed, in their order; a model its service
     * no longer offers is left out.
     *
     * @return list<MediaModelData>
     */
    public function imageModels(): array
    {
        return $this->listed('image_models', $this->imageModelsOf(...));
    }

    /**
     * The video models the admin listed, in their order.
     *
     * @return list<MediaModelData>
     */
    public function videoModels(): array
    {
        return $this->listed('video_models', $this->videoModelsOf(...));
    }

    /** The image model used unless a chatter picks another: the admin's default, else the first listed. */
    public function defaultImage(): ?MediaModelData
    {
        return $this->defaultOf($this->imageModels(), 'default_image_model');
    }

    /** The video model used unless a chatter picks another. */
    public function defaultVideo(): ?MediaModelData
    {
        return $this->defaultOf($this->videoModels(), 'default_video_model');
    }

    /** Whether agents can make pictures: at least one image model is listed. */
    public function imageEnabled(): bool
    {
        return $this->imageModels() !== [];
    }

    /** Whether agents can make videos: at least one video model is listed. */
    public function videoEnabled(): bool
    {
        return $this->videoModels() !== [];
    }

    /**
     * The services that make pictures.
     *
     * @return list<AiProvider>
     */
    public function imageServices(): array
    {
        return [AiProvider::OpenAi, AiProvider::OpenRouter, AiProvider::Google, AiProvider::Fal];
    }

    /**
     * The services that make videos.
     *
     * @return list<AiProvider>
     */
    public function videoServices(): array
    {
        return collect(array_keys(self::VIDEO_MODELS))->map(AiProvider::from(...))->all();
    }

    /**
     * The image models a service offers, for the admin to add.
     *
     * @return array<string, string> model id => label for the admin
     */
    public function imageModelsOf(AiProvider $service): array
    {
        return $service === AiProvider::OpenRouter ? $this->openRouter->options() : self::IMAGE_MODELS[$service->value] ?? [];
    }

    /**
     * The video models a service offers, for the admin to add.
     *
     * @return array<string, string> model id => label for the admin
     */
    public function videoModelsOf(AiProvider $service): array
    {
        return self::VIDEO_MODELS[$service->value] ?? [];
    }

    /**
     * @param  Closure(AiProvider): array<string, string>  $offered
     * @return list<MediaModelData>
     */
    private function listed(string $key, Closure $offered): array
    {
        $models = collect((array) config("chatvia.ai.{$key}"))
            ->map(fn (mixed $entry): string => (string) $entry)
            ->unique()
            ->map(function (string $entry) use ($offered): ?MediaModelData {
                $service = AiProvider::tryFrom(Str::before($entry, ':'));
                $model = Str::after($entry, ':');

                if (! $service instanceof AiProvider || $model === '' || $model === $entry) {
                    return null;
                }

                $label = $offered($service)[$model] ?? null;

                if ($label === null && $service !== AiProvider::OpenRouter) {
                    return null;
                }

                return new MediaModelData($service, $model, $label ?? $model, $this->nameOf($service, $model, $label));
            })
            ->filter()
            ->values();

        return $this->withServiceOnSharedNames($models)->all();
    }

    /**
     * Two services can offer the same model ("Google Veo 3.1 Fast" on Runway
     * and fal.ai), so a name more than one model has also says the service.
     *
     * @param  Collection<int, MediaModelData>  $models
     * @return Collection<int, MediaModelData>
     */
    private function withServiceOnSharedNames(Collection $models): Collection
    {
        $shared = $models->countBy(fn (MediaModelData $model): string => $model->name)->filter(fn (int $count): bool => $count > 1);

        return $models->map(fn (MediaModelData $model): MediaModelData => $shared->has($model->name)
            ? new MediaModelData($model->service, $model->model, $model->label, "{$model->name} ({$model->service->label()})")
            : $model);
    }

    /**
     * @param  list<MediaModelData>  $models
     */
    private function defaultOf(array $models, string $key): ?MediaModelData
    {
        $picked = (string) config("chatvia.ai.{$key}");

        return collect($models)->first(fn (MediaModelData $model): bool => $model->key() === $picked) ?? $models[0] ?? null;
    }

    /** A model's name for chatters: its admin label without the notes, or its id. */
    private function nameOf(AiProvider $service, string $model, ?string $label): string
    {
        if ($label === null) {
            return $model;
        }

        // OpenRouter's own picks carry a note after a colon; its other
        // models are named "Maker: Model", which stays whole.
        if ($service === AiProvider::OpenRouter && array_key_exists($model, OpenRouterImageModels::CURATED)) {
            return Str::before($label, ':');
        }

        return Str::of($label)->before(' (')->trim()->toString();
    }
}
