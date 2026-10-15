<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use App\Admin\Settings\Data\StorageSetupData;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Checks S3-compatible storage before it is saved: writes a small file to
 * the bucket, reads it back and deletes it, with the address and keys typed
 * in the admin setup.
 */
final class StorageCheck
{
    /** Why the storage does not work, or null when it does. */
    public function check(StorageSetupData $storage): ?string
    {
        if (! $storage->usesS3()) {
            return null;
        }

        $path = 'chatvia-storage-test-'.Str::random(12).'.txt';
        $content = 'Chatvia storage test '.now()->toIso8601String();

        try {
            $disk = Storage::build([
                'driver' => 's3',
                'key' => $storage->key,
                'secret' => $storage->secret,
                'region' => $storage->region,
                'endpoint' => $storage->endpoint,
                'use_path_style_endpoint' => $storage->pathStyle,
                'bucket' => $storage->bucket,
                'throw' => true,
            ]);

            $disk->put($path, $content);
            $read = $disk->get($path);
            $disk->delete($path);
        } catch (Throwable $e) {
            return 'The bucket failed: '.Str::limit($e->getMessage(), 300);
        }

        return $read === $content ? null : 'The bucket returned other content than was written.';
    }
}
