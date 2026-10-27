<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Identity\Workspace\Models\Workspace;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Services\ChatStoragePath;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A one-page PDF a visitor attached to a new conversation, not yet tied to a
 * message. Only the row is made unless {@see stored()} also writes the file,
 * in the conversation's chat folder ({@see ChatStoragePath}).
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
            'filepath' => fn (array $attributes): string => self::pathIn($attributes),
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

    /**
     * An upload in the composer, before any conversation exists: in the
     * agent's pending folder, the one the chat checks before deleting it,
     * or in a new workspace when no agent is given.
     */
    public function pending(?Agent $agent = null): static
    {
        return $this->state([
            'chat_conversation_id' => null,
            'chat_message_id' => null,
            'workspace_id' => $agent->workspace_id ?? Workspace::factory(),
        ])->state(fn (array $attributes): array => $agent instanceof Agent
            ? ['filepath' => ChatStoragePath::pending($agent).'/files/'.Str::uuid7().'.pdf']
            : []);
    }

    public function forMessage(Message $message): static
    {
        return $this->state([
            'chat_conversation_id' => $message->chat_conversation_id,
            'chat_message_id' => $message->id,
            'workspace_id' => $message->workspace_id,
        ]);
    }

    /**
     * The conversation's `files` folder, or the workspace's when there is no
     * conversation (a pending upload made without an agent).
     *
     * @param  array<string, mixed>  $attributes
     */
    private static function pathIn(array $attributes): string
    {
        $conversation = Conversation::query()->find($attributes['chat_conversation_id'] ?? null);
        $agent = $conversation instanceof Conversation ? Agent::withTrashed()->find($conversation->agent_id) : null;

        if ($conversation instanceof Conversation && $agent instanceof Agent) {
            return ChatStoragePath::forConversation($agent, $conversation->external_id, 'files').'/'.Str::uuid7().'.pdf';
        }

        return 'workspaces/'.Workspace::query()->whereKey($attributes['workspace_id'])->value('external_id').'/'.Str::uuid7().'.pdf';
    }

    /** With its file written to the attachments disk (fake it first). */
    public function stored(string $contents = '%PDF-1.4 test'): static
    {
        return $this->afterCreating(fn (Attachment $attachment) => Storage::disk(Attachment::DISK)->put($attachment->filepath, $contents));
    }
}
