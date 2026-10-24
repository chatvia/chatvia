<?php

namespace Domain\Conversation\Models;

use Database\Factories\AttachmentFactory;
use Domain\Knowledge\Document\Concerns\HasStorageFile;
use Domain\Llm\Enums\FileInput;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Support\Concerns\HasExternalId;
use Throwable;

/**
 * Visitor- or member-uploaded file attached to a conversation: a PDF or an
 * image, as the model for chat files reads them (that model gets the file
 * inside each request).
 *
 * Stored on the private disk and opened through a signed link (the
 * `attachments.view` route). Deleting a row (including via the
 * conversation cascade in {@see Conversation::booted}) wipes the stored
 * file once the delete commits.
 *
 * @property int $id
 * @property string $external_id
 * @property int $workspace_id
 * @property int|null $chat_conversation_id
 * @property int|null $chat_message_id
 * @property string $filepath
 * @property string $filename
 * @property string $mime_type
 * @property int $size_bytes
 * @property int|null $pages PDF page count (null for non-PDFs)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $url Signed link that opens the file
 * @property-read Conversation|null $conversation
 * @property-read Message|null $message
 */
#[Fillable([
    'workspace_id',
    'chat_conversation_id',
    'chat_message_id',
    'filepath',
    'filename',
    'mime_type',
    'size_bytes',
    'pages',
])]
#[Table(name: 'chat_attachments')]
#[RouteKey('external_id')]
class Attachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
    use HasExternalId, HasFactory, HasStorageFile;

    /** The disk every chat attachment is stored on. */
    public const string DISK = 'private';

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'chat_conversation_id');
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'chat_message_id');
    }

    /**
     * Whether a browser may show the file in place: a PDF or one of the
     * image types chatters may attach (never SVG, which can carry script).
     */
    public function opensInline(): bool
    {
        return FileInput::forMimeType($this->mime_type) instanceof FileInput;
    }

    /**
     * The link that opens the file. It never expires (chat history keeps
     * it) and its signature leaves out the host, so it works in the hosted
     * chat, the widget on another site and the dashboard alike.
     *
     * @return Attribute<string, never>
     */
    public function url(): Attribute
    {
        return Attribute::get(fn (): string => url(URL::signedRoute('attachments.view', ['attachment' => $this], absolute: false)));
    }

    /**
     * Wipe the stored file whenever a row is deleted — including via the
     * conversation cascade triggered in {@see Conversation::booted}. The
     * object goes only once the delete commits (at once when there is no
     * transaction), so a rolled-back delete keeps the file its row points to.
     */
    protected static function booted(): void
    {
        static::deleted(static function (Attachment $attachment): void {
            $id = $attachment->id;
            $filepath = $attachment->filepath;

            DB::afterCommit(static function () use ($id, $filepath): void {
                try {
                    Storage::disk(self::DISK)->delete($filepath);
                } catch (Throwable $e) {
                    // A remote-storage failure must not surface after the
                    // rows are gone; the daily prune sweeps orphaned objects.
                    Log::warning('Failed to delete chat attachment object', [
                        'attachment_id' => $id,
                        'filepath' => $filepath,
                        'exception' => $e::class,
                        'message' => $e->getMessage(),
                    ]);
                }
            });
        });
    }
}
