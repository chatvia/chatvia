<?php

declare(strict_types=1);

namespace Domain\Conversation\Models;

use Database\Factories\GeneratedMediaFactory;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\GeneratedMediaFailure;
use Domain\Conversation\Enums\GeneratedMediaKind;
use Domain\Conversation\Enums\GeneratedMediaStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Support\Concerns\HasExternalId;
use Throwable;

/**
 * An image, audio file or video an agent made in a conversation. The row is created
 * as soon as the agent asks for it (pending) and a background job makes the
 * file, so a reload never loses it. The reply that asked for it points at
 * the row through its artifact (`metadata.artifacts[].data.id` = external id),
 * not the other way round: the reply's own row is written later, or never
 * when the chat log is off.
 *
 * Kept apart from {@see Attachment}, which means "a file the visitor
 * uploaded" to the model call, the upload limit and the composer.
 *
 * Stored on the private disk under the agent's folder and opened through a
 * signed link (the `generated-media.view` route). Deleting a row wipes the
 * stored file once the delete commits.
 *
 * @property int $id
 * @property string $external_id
 * @property int $workspace_id
 * @property int $agent_id
 * @property int $chat_conversation_id
 * @property GeneratedMediaKind $kind
 * @property GeneratedMediaStatus $status
 * @property string $prompt The picture asked for, or the text to speak
 * @property array{shape?: string, seconds?: int, resolution?: string, voice?: string, model?: string, model_name?: string}|null $metadata What it is made with: an image's shape, a video's shape, seconds and resolution, or the voice an audio file is read in; the model that makes a picture or a video, and its name when the chatter picked it from the agent's choice
 * @property string $service The service that makes it (an `AiProvider` value; an audio file also names its model), so the job and each video check go to it
 * @property string|null $task_id A video's task at its service, while it is being made
 * @property string|null $filepath
 * @property string|null $mime_type
 * @property int|null $size_bytes
 * @property GeneratedMediaFailure|null $failure
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $url Signed link that opens the file
 * @property-read Agent|null $agent
 * @property-read Conversation|null $conversation
 */
#[Fillable([
    'workspace_id',
    'agent_id',
    'chat_conversation_id',
    'kind',
    'status',
    'prompt',
    'metadata',
    'service',
    'task_id',
    'filepath',
    'mime_type',
    'size_bytes',
    'failure',
])]
#[Table(name: 'chat_generated_media')]
#[RouteKey('external_id')]
class GeneratedMedia extends Model
{
    /** @use HasFactory<GeneratedMediaFactory> */
    use HasExternalId, HasFactory;

    /** The disk every generated file is stored on. */
    public const string DISK = 'private';

    /**
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'chat_conversation_id');
    }

    /**
     * The link that opens the file. Like {@see Attachment::url()}, it never
     * expires and its signature leaves out the host, so it works in the
     * hosted chat and the dashboard alike. It ends with the file's extension,
     * or the kind's usual one while the file is not made yet.
     *
     * @return Attribute<string, never>
     */
    public function url(): Attribute
    {
        return Attribute::get(fn (): string => url(URL::signedRoute('generated-media.view', [
            'generatedMedia' => $this,
            'extension' => File::extension($this->filepath ?? '') ?: $this->kind->extension(),
        ], absolute: false)));
    }

    /** When a started video has had all its time (`chatvia.ai.video_max_wait_minutes`). */
    public function videoDeadline(): Carbon
    {
        return ($this->created_at ?? now())->copy()->addMinutes(max(1, (int) config('chatvia.ai.video_max_wait_minutes')));
    }

    protected function casts(): array
    {
        return [
            'kind' => GeneratedMediaKind::class,
            'status' => GeneratedMediaStatus::class,
            'failure' => GeneratedMediaFailure::class,
            'metadata' => 'array',
            'size_bytes' => 'integer',
        ];
    }

    /**
     * Wipe the stored file whenever a row is deleted. The file goes only once
     * the delete commits, so a rolled-back delete keeps the file its row
     * points to.
     */
    protected static function booted(): void
    {
        static::deleted(static function (GeneratedMedia $media): void {
            $id = $media->id;
            $filepath = $media->filepath;

            if (blank($filepath)) {
                return;
            }

            DB::afterCommit(static function () use ($id, $filepath): void {
                try {
                    Storage::disk(self::DISK)->delete($filepath);
                } catch (Throwable $e) {
                    // A remote-storage failure must not surface after the
                    // row is gone; purging the agent wipes its folder.
                    Log::warning('Failed to delete generated media object', [
                        'generated_media_id' => $id,
                        'filepath' => $filepath,
                        'exception' => $e::class,
                        'message' => $e->getMessage(),
                    ]);
                }
            });
        });
    }
}
