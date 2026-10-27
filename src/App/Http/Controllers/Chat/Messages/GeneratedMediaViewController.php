<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat\Messages;

use Domain\Conversation\Enums\GeneratedMediaKind;
use Domain\Conversation\Enums\GeneratedMediaStatus;
use Domain\Conversation\Models\GeneratedMedia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Serves an image, audio file or video an agent made in a conversation.
 * Public like {@see AttachmentViewController}, so only the signed link the
 * row makes is accepted ({@see GeneratedMedia::url()}).
 *
 * Sent as a file response, which answers byte-range requests: browsers ask
 * for parts of an audio file or video to play and seek it (Safari will not
 * play one otherwise). A picture or audio file on remote storage is copied
 * to a temp file first; they are small. A video there is sent to a
 * short-lived link of the storage itself, which answers ranges on its own,
 * so a seek never copies the whole video again.
 */
class GeneratedMediaViewController
{
    /** The types a browser may show in place; anything else downloads. */
    private const array INLINE_TYPES = ['image/png', 'image/jpeg', 'image/webp', 'audio/mpeg', 'audio/wav', 'video/mp4', 'video/webm'];

    /** How long the storage's own link to a video lasts. */
    private const int VIDEO_LINK_MINUTES = 60;

    public function __invoke(GeneratedMedia $generatedMedia): BinaryFileResponse|RedirectResponse
    {
        $path = $generatedMedia->filepath ?? '';
        $disk = Storage::disk(GeneratedMedia::DISK);

        abort_unless($generatedMedia->status === GeneratedMediaStatus::Ready && $path !== '' && $disk->exists($path), 404);

        $isLocal = $disk->getAdapter() instanceof LocalFilesystemAdapter;

        if (! $isLocal && $generatedMedia->kind === GeneratedMediaKind::Video && $disk->providesTemporaryUrls()) {
            return redirect()->away($disk->temporaryUrl($path, now()->addMinutes(self::VIDEO_LINK_MINUTES), [
                'ResponseContentType' => $generatedMedia->mime_type ?? 'video/mp4',
            ]));
        }

        $file = $isLocal ? $disk->path($path) : create_temp_file($path, (string) $disk->get($path));
        $mimeType = $generatedMedia->mime_type ?? 'application/octet-stream';

        $response = new BinaryFileResponse($file, 200, [
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
        ]);

        $response->setContentDisposition(
            collect(self::INLINE_TYPES)->containsStrict($mimeType) ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            "{$generatedMedia->kind->value}-{$generatedMedia->external_id}.".Str::afterLast($path, '.'),
        );

        return $isLocal ? $response : $response->deleteFileAfterSend();
    }
}
