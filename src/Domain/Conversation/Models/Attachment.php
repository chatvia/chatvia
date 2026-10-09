<?php

namespace Domain\Conversation\Models;

use Domain\Knowledge\Document\Concerns\HasStorageFile;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Support\Concerns\HasExternalId;
use Throwable;

/**
 * Visitor- or member-uploaded file attached to a conversation. Used for PDF
 * Q&A (Mistral document_url chat); the shape is generic enough to back image
 * or audio attachments too.
 *
 * Stored on the public object-storage disk so Mistral and other URL-consuming
 * MCP tools can fetch the file directly. Deleting a row (including via the
 * conversation cascade in {@see Conversation::booted}) wipes the
 * underlying S3 object once the delete commits.
 *
 * @property int $id
 * @property string $external_id
 * @property int|null $chat_conversation_id
 * @property int|null $chat_message_id
 * @property string $filepath
 * @property string $filename
 * @property string $mime_type
 * @property int $size_bytes
 * @property int|null $pages PDF page count (null for non-PDFs)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $url Public URL on the public disk
 * @property-read Conversation|null $conversation
 * @property-read Message|null $message
 */
#[Fillable([
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
    use HasExternalId, HasStorageFile;

    /**
     * Public bucket every chat attachment lives on. Hardcoded rather than a
     * per-row column because we always want public URLs (Mistral et al.
     * fetch them server-side); a private disk would need pre-signing per
     * request, which is wasted overhead for the use case.
     */
    public const DISK = 'public';

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

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * Derived public URL. HasStorageFile's read helpers operate on the
     * default disk; ours live on the public disk, so we expose the URL via
     * an accessor instead of storing it.
     *
     * @return Attribute<string, never>
     */
    public function url(): Attribute
    {
        return Attribute::get(fn (): string => Storage::disk(self::DISK)->url($this->filepath));
    }

    /**
     * Wipe the S3 object whenever a row is deleted — including via the
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
