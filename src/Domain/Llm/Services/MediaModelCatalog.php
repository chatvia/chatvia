<?php

declare(strict_types=1);

namespace Domain\Llm\Services;

use Illuminate\Support\Str;

/**
 * The image and video models each service offers, with a label for the
 * admin (Settings → AI models) and a short name for chatters who pick one in
 * the hosted chat. Each generator's `MODELS` lists the same ids. OpenRouter's
 * image models come from {@see OpenRouterImageModels}, as they change often.
 * Only the service the admin chose makes pictures or videos, so the models
 * offered are always that service's.
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

    /** Services whose `services.*` config key differs from the service name. */
    private const array CONFIG_KEYS = ['google' => 'gemini'];

    public function __construct(
        private OpenRouterImageModels $openRouter,
    ) {}

    /** The image service in use (Settings → AI models), or `none`. */
    public function imageService(): string
    {
        return $this->driver('image_generator');
    }

    /** The video service in use (Settings → AI models), or `none`. */
    public function videoService(): string
    {
        return $this->driver('video_generator');
    }

    /**
     * @return array<string, string> model id => label for the admin
     */
    public function imageModels(?string $service = null): array
    {
        $service ??= $this->imageService();

        return $service === 'openrouter' ? $this->openRouter->options() : self::IMAGE_MODELS[$service] ?? [];
    }

    /**
     * @return array<string, string> model id => label for the admin
     */
    public function videoModels(?string $service = null): array
    {
        return self::VIDEO_MODELS[$service ?? $this->videoService()] ?? [];
    }

    /** The model the admin picked for the image service in use; empty while it is off. */
    public function defaultImageModel(): string
    {
        return $this->configured($this->imageService(), 'image_model');
    }

    /** The model the admin picked for the video service in use; empty while it is off. */
    public function defaultVideoModel(): string
    {
        return $this->configured($this->videoService(), 'video_model');
    }

    /** A model's name for chatters: its admin label without the notes, or its id. */
    public function imageName(string $model): string
    {
        $label = $this->imageModels()[$model] ?? null;

        if ($label === null) {
            return $model;
        }

        // OpenRouter's own picks carry a note after a colon; its other
        // models are named "Maker: Model", which stays whole.
        return array_key_exists($model, OpenRouterImageModels::CURATED)
            ? Str::before($label, ':')
            : $this->withoutNote($label);
    }

    /** A model's name for chatters: its admin label without the notes, or its id. */
    public function videoName(string $model): string
    {
        $label = $this->videoModels()[$model] ?? null;

        return $label === null ? $model : $this->withoutNote($label);
    }

    private function withoutNote(string $label): string
    {
        return Str::of($label)->before(' (')->trim()->toString();
    }

    private function driver(string $key): string
    {
        $driver = Str::of((string) config("chatvia.ai.{$key}"))->trim()->lower()->toString();

        return $driver === '' || $driver === 'auto' ? 'none' : $driver;
    }

    private function configured(string $service, string $key): string
    {
        if ($service === 'none') {
            return '';
        }

        return (string) config('services.'.(self::CONFIG_KEYS[$service] ?? $service).'.'.$key);
    }
}
