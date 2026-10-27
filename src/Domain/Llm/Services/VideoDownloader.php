<?php

declare(strict_types=1);

namespace Domain\Llm\Services;

use Domain\Llm\Data\Video\GeneratedVideoData;
use Domain\Llm\Exceptions\VideoGenerationFailedException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Saves a made video to a temp file: fetched from the link a video service
 * sends (streamed to disk, so a large video never sits in memory), or
 * decoded when the service sends it inline. A failed fetch leaves no file
 * behind.
 */
final readonly class VideoDownloader
{
    private const int TIMEOUT_SECONDS = 180;

    /**
     * @param  array<string, string>  $headers  only for links on the service's own host, such as Google's key
     *
     * @throws VideoGenerationFailedException
     */
    public function fromUrl(string $url, string $service, array $headers = []): GeneratedVideoData
    {
        $path = $this->tempPath();

        try {
            $response = Http::withHeaders($headers)->timeout(self::TIMEOUT_SECONDS)->sink($path)->get($url)->throw();
        } catch (Throwable $e) {
            File::delete($path);

            throw new VideoGenerationFailedException("The video {$service} made could not be fetched: ".$e->getMessage(), previous: $e);
        }

        return new GeneratedVideoData($path, $this->mimeType($response->header('Content-Type')));
    }

    /** @throws VideoGenerationFailedException */
    public function fromBase64(string $data, string $mimeType, string $service): GeneratedVideoData
    {
        $bytes = base64_decode($data, true);

        if ($bytes === false || $bytes === '') {
            throw new VideoGenerationFailedException("{$service} sent a video that could not be read.");
        }

        $path = $this->tempPath();
        File::put($path, $bytes);

        return new GeneratedVideoData($path, $this->mimeType($mimeType));
    }

    private function tempPath(): string
    {
        return create_temp_dir('/generated-videos').'/'.Str::uuid7().'.video';
    }

    /** The type the service named, or MP4: every service here makes MP4 files. */
    private function mimeType(string $named): string
    {
        $type = Str::lower(Str::trim(Str::before($named, ';')));

        return Str::startsWith($type, 'video/') ? $type : 'video/mp4';
    }
}
