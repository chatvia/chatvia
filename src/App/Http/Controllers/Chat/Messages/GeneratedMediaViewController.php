<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat\Messages;

use Domain\Conversation\Enums\GeneratedMediaStatus;
use Domain\Conversation\Models\GeneratedMedia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Serves an image or audio file an agent made in a conversation. Public like
 * {@see AttachmentViewController}, so only the signed link the row makes is
 * accepted ({@see GeneratedMedia::url()}).
 *
 * Sent as a file response, which answers byte-range requests: browsers ask
 * for parts of an audio file to play and seek it (Safari will not play one
 * otherwise). A file on remote storage is copied to a temp file first;
 * generated files are small.
 */
class GeneratedMediaViewController
{
    /** The types a browser may show in place; anything else downloads. */
    private const array INLINE_TYPES = ['image/png', 'image/jpeg', 'image/webp', 'audio/mpeg', 'audio/wav'];

    public function __invoke(GeneratedMedia $generatedMedia): BinaryFileResponse
    {
        $path = $generatedMedia->filepath ?? '';
        $disk = Storage::disk(GeneratedMedia::DISK);

        abort_unless($generatedMedia->status === GeneratedMediaStatus::Ready && $path !== '' && $disk->exists($path), 404);

        $isLocal = $disk->getAdapter() instanceof LocalFilesystemAdapter;
        $file = $isLocal ? $disk->path($path) : create_temp_file($path, (string) $disk->get($path));
        $mimeType = $generatedMedia->mime_type ?? 'application/octet-stream';

        $response = new BinaryFileResponse($file, 200, [
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);

        $response->setContentDisposition(
            in_array($mimeType, self::INLINE_TYPES, true) ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $generatedMedia->kind->value.'-'.$generatedMedia->external_id.'.'.Str::afterLast($path, '.'),
        );

        return $isLocal ? $response : $response->deleteFileAfterSend();
    }
}
