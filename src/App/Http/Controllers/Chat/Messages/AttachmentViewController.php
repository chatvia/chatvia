<?php

declare(strict_types=1);

namespace App\Http\Controllers\Chat\Messages;

use Domain\Conversation\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves a file attached to a conversation. Public, because the hosted chat,
 * the widget on another site and the dashboard all open the same link, so
 * the route only accepts the signed URL the attachment itself makes
 * ({@see Attachment::url()}). A PDF opens in the browser; anything else
 * downloads, so an uploaded file never runs as a page of this site.
 */
class AttachmentViewController
{
    public function __invoke(Attachment $attachment): StreamedResponse
    {
        $disk = Storage::disk(Attachment::DISK);

        abort_unless($disk->exists($attachment->filepath), 404);

        return $disk->response(
            $attachment->filepath,
            $attachment->filename,
            [
                'Content-Type' => $attachment->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, max-age=3600',
            ],
            $attachment->opensInline() ? 'inline' : 'attachment',
        );
    }
}
