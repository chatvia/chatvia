<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use App\Admin\Settings\Data\S3BucketData;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Checks S3-compatible storage before it is saved: writes a small file to a
 * bucket, reads it back and deletes it, with the bucket's address and keys
 * typed in the admin setup. For the public bucket, the file must also load from its
 * public URL, or profile photos and logos would not show.
 */
final class StorageCheck
{
    /**
     * Why the bucket does not work, or null when it does. A bucket with a
     * URL must also serve its files there.
     */
    public function check(S3BucketData $bucket): ?string
    {
        try {
            $disk = Storage::build([
                'driver' => 's3',
                'key' => $bucket->key,
                'secret' => $bucket->secret,
                'region' => $bucket->region,
                'endpoint' => $bucket->endpoint,
                'use_path_style_endpoint' => $bucket->pathStyle,
                'bucket' => $bucket->bucket,
                'throw' => true,
            ]);
        } catch (Throwable $e) {
            return "The bucket {$bucket->bucket} failed: ".Str::limit($e->getMessage(), 300);
        }

        return $this->checkDisk($disk, "The bucket {$bucket->bucket}", $bucket->url);
    }

    /**
     * Write, read and delete a small file on a disk; with a public URL, also
     * load the file from it. Why it failed, or null. `$name` starts the
     * message, as in "The bucket files".
     */
    public function checkDisk(Filesystem $disk, string $name, ?string $publicUrl = null): ?string
    {
        $path = 'chatvia-storage-test-'.Str::random(12).'.txt';
        $content = 'Chatvia storage test '.now()->toIso8601String();

        try {
            $disk->put($path, $content);

            try {
                if ($disk->get($path) !== $content) {
                    return "{$name} returned other content than was written.";
                }

                if (filled($publicUrl) && ! $this->loadsPublicly(Str::finish($publicUrl, '/').$path, $content)) {
                    return "{$name} works, but its files do not load from {$publicUrl}. Turn on public read access for the bucket, and check the public URL.";
                }
            } finally {
                $disk->delete($path);
            }
        } catch (Throwable $e) {
            return "{$name} failed: ".Str::limit($e->getMessage(), 300);
        }

        return null;
    }

    private function loadsPublicly(string $url, string $content): bool
    {
        return rescue(fn (): bool => Http::timeout(10)->get($url)->body() === $content, false, report: false);
    }
}
