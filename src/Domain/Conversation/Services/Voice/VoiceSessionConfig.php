<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Voice;

use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Data\Voice\VoiceCallerData;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Conversation\Services\Turn\SystemPromptBuilder;
use Domain\Integration\Tool\Data\ToolEntry;
use Domain\Llm\Contracts\RealtimeCalls;
use Domain\Llm\Enums\RealtimeVoice;
use Illuminate\Support\Str;

/**
 * The settings a voice call starts with: the model, the agent's
 * instructions for speaking (with the messages typed before the call), what
 * the visitor says written down, the voice, and the tools.
 *
 * The call starts with turn detection off, so the model cannot answer
 * before the worker has joined: the worker turns it on ({@see
 * turnDetection()}), and the browser waits for that before it listens.
 * Replies are created by the worker too (`create_response: false`), so the
 * message limits are checked before every reply.
 */
final readonly class VoiceSessionConfig
{
    /** Longest message carried into the call's history, in characters. */
    private const int HISTORY_MESSAGE_LENGTH = 2000;

    public function __construct(
        private RealtimeCalls $calls,
        private SystemPromptBuilder $prompts,
        private VoiceToolCatalog $tools,
        private ChatMemory $memory,
    ) {}

    /**
     * @param  array<string, ToolEntry>  $tools  the call's tools, by name ({@see VoiceToolCatalog::for()})
     * @return array<string, mixed>
     */
    public function build(VoiceCallerData $caller, RealtimeVoice $voice, array $tools): array
    {
        $agent = $caller->agent;

        return [
            'type' => 'realtime',
            'model' => $this->calls->model(),
            'instructions' => $this->prompts->voicePrompt($agent, $caller->chatterName, $this->history($caller)),
            'output_modalities' => ['audio'],
            'audio' => [
                'input' => [
                    'transcription' => ['model' => (string) config('services.openai.realtime_transcription_model')],
                    'noise_reduction' => ['type' => 'near_field'],
                    'turn_detection' => null,
                ],
                'output' => ['voice' => $voice->value],
            ],
            'tools' => $this->tools->definitions($agent, $tools),
            'tool_choice' => 'auto',
            // Drop older audio in bigger steps when the context fills, which
            // keeps the cached prefix longer (cheaper turns).
            'truncation' => ['type' => 'retention_ratio', 'retention_ratio' => 0.8],
        ];
    }

    /**
     * The `session.update` the worker sends once it has joined: listen, and
     * let the visitor interrupt, but leave creating the reply to the worker.
     *
     * @return array<string, mixed>
     */
    public static function turnDetection(): array
    {
        return [
            'type' => 'session.update',
            'session' => [
                'type' => 'realtime',
                'audio' => [
                    'input' => [
                        'turn_detection' => [
                            'type' => 'semantic_vad',
                            'create_response' => false,
                            'interrupt_response' => true,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * The conversation's last messages as they were shown (not with the
     * reference material a typed turn adds), so the call carries on from
     * them.
     *
     * @return array<int, array{role: string, content: string}>
     */
    private function history(VoiceCallerData $caller): array
    {
        $limit = max(0, (int) config('chatvia.agent.history_messages', 3));

        if ($limit === 0) {
            return [];
        }

        $messages = $this->memory
            ->withScope(new ChatScopeData($caller->agent, $caller->sessionId, $caller->conversationId))
            ->cachedMessages();

        return collect($messages)
            ->filter(static fn (MessageData $message): bool => in_array($message->role, [MessageRole::User, MessageRole::Assistant], true) && filled($message->content))
            ->slice(-$limit)
            ->map(static fn (MessageData $message): array => [
                'role' => $message->role->value,
                'content' => Str::limit($message->content, self::HISTORY_MESSAGE_LENGTH),
            ])
            ->values()
            ->all();
    }
}
