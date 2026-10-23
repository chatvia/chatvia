<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Identity\Workspace\Models\Workspace;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A one-page PDF a visitor attached to a new conversation, not yet tied to a
 * message. Only the row is made unless {@see stored()} also writes the file,
 * under the workspace's own folder (`workspaces/{ext}/…`).
 *
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    #[\Override]
    protected $model = Attachment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chat_conversation_id' => Conversation::factory(),
            'workspace_id' => fn (array $attributes): int => (int) Conversation::query()->whereKey($attributes['chat_conversation_id'])->value('workspace_id'),
            'chat_message_id' => null,
            'filepath' => fn (array $attributes): string => 'workspaces/'.Workspace::query()->whereKey($attributes['workspace_id'])->value('external_id').'/attachments/'.Str::uuid().'.pdf',
            'filename' => 'notes.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'pages' => 1,
        ];
    }

    public function forConversation(Conversation $conversation): static
    {
        return $this->state(['chat_conversation_id' => $conversation->id, 'workspace_id' => $conversation->workspace_id]);
    }

    public function forMessage(Message $message): static
    {
        return $this->state([
            'chat_conversation_id' => $message->chat_conversation_id,
            'chat_message_id' => $message->id,
            'workspace_id' => $message->workspace_id,
        ]);
    }

    /** With its file written to the attachments disk (fake it first). */
    public function stored(string $contents = '%PDF-1.4 test'): static
    {
        return $this->afterCreating(fn (Attachment $attachment) => Storage::disk(Attachment::DISK)->put($attachment->filepath, $contents));
    }
}
