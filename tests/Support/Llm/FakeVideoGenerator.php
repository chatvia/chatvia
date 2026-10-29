<?php

declare(strict_types=1);

namespace Tests\Support\Llm;

use Domain\Llm\Contracts\VideoGenerator;
use Domain\Llm\Data\Media\MediaModelData;
use Domain\Llm\Data\Video\GeneratedVideoData;
use Domain\Llm\Data\Video\VideoPromptData;
use Domain\Llm\Enums\AiProvider;
use Domain\Llm\Operations\Media\Video\ElevenLabsVideoGenerator;
use Domain\Llm\Operations\Media\Video\FalVideoGenerator;
use Domain\Llm\Operations\Media\Video\GoogleVideoGenerator;
use Domain\Llm\Operations\Media\Video\KlingVideoGenerator;
use Domain\Llm\Operations\Media\Video\RunwayVideoGenerator;
use Domain\Llm\Operations\Media\Video\SeedanceVideoGenerator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

/**
 * Offline stand-in for a video service: it is set up, starts each prompt as
 * task "fake-task-{n}", and answers each check from its script, in order:
 * null (still being made), a string (made: the video's bytes, saved to a
 * temp file) or a Throwable (thrown). It remembers what it started and what
 * it forgot.
 */
final class FakeVideoGenerator implements VideoGenerator
{
    /** @var list<VideoPromptData> */
    public array $started = [];

    /** @var list<string> */
    public array $forgotten = [];

    /**
     * @param  list<string|Throwable|null>  $checks
     */
    public function __construct(
        private array $checks = [],
        private readonly ?Throwable $startFailure = null,
    ) {}

    public function start(VideoPromptData $prompt): string
    {
        if ($this->startFailure instanceof Throwable) {
            throw $this->startFailure;
        }

        $this->started[] = $prompt;

        return 'fake-task-'.count($this->started);
    }

    public function check(string $taskId): ?GeneratedVideoData
    {
        $answer = array_shift($this->checks);

        if ($answer instanceof Throwable) {
            throw $answer;
        }

        if ($answer === null) {
            return null;
        }

        $path = create_temp_dir('/fake-videos').'/'.Str::uuid7().'.mp4';
        File::put($path, $answer);

        return new GeneratedVideoData($path, 'video/mp4');
    }

    public function forget(string $taskId): void
    {
        $this->forgotten[] = $taskId;
    }

    /**
     * Bind it as the generator of one video service, and list that
     * service's model (none when $enabled is false), so agents can make
     * videos; return it to check what it did.
     *
     * @param  list<string|Throwable|null>  $checks
     */
    public static function install(array $checks = [], ?Throwable $startFailure = null, bool $enabled = true, AiProvider $service = AiProvider::Fal, string $model = 'fal-ai/wan/v2.2-a14b/text-to-video'): self
    {
        $fake = new self($checks, $startFailure);
        app()->instance(match ($service) {
            AiProvider::Google => GoogleVideoGenerator::class,
            AiProvider::ElevenLabs => ElevenLabsVideoGenerator::class,
            AiProvider::Kling => KlingVideoGenerator::class,
            AiProvider::BytePlus => SeedanceVideoGenerator::class,
            AiProvider::Runway => RunwayVideoGenerator::class,
            default => FalVideoGenerator::class,
        }, $fake);
        config(['chatvia.ai.video_models' => $enabled ? [MediaModelData::keyOf($service, $model)] : []]);

        return $fake;
    }
}
