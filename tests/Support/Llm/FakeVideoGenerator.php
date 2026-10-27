<?php

declare(strict_types=1);

namespace Tests\Support\Llm;

use Domain\Llm\Contracts\VideoGenerator;
use Domain\Llm\Data\Video\GeneratedVideoData;
use Domain\Llm\Data\Video\VideoPromptData;
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
        private readonly bool $enabled = true,
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

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function name(): string
    {
        return 'fake video-model';
    }

    /**
     * Bind it as the video service, and return it to check what it did.
     *
     * @param  list<string|Throwable|null>  $checks
     */
    public static function install(array $checks = [], ?Throwable $startFailure = null, bool $enabled = true): self
    {
        $fake = new self($checks, $startFailure, $enabled);
        app()->instance(VideoGenerator::class, $fake);

        return $fake;
    }
}
