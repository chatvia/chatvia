<?php

namespace Domain\Agent\Services;

use Domain\Agent\Models\Agent;
use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Enums\LlmRole;
use Domain\Llm\Services\LlmChat;
use Domain\Llm\Services\ModelRoles;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Suggests a free slug for an agent's Hosted Chat address (`/chat/{slug}`):
 * the Fast model (LlmRole::Fast) proposes a short playful name,
 * which is cleaned to lowercase letters, digits and inner dashes. A taken or
 * unusable suggestion is retried a few times; after that — or when the model
 * is unreachable — a random `agent-…` name is used, so the button always gets
 * an answer.
 */
class AgentSlugGenerator
{
    private const int ATTEMPTS = 3;

    private const int MIN_LENGTH = 3;

    private const int MAX_LENGTH = 63;

    /**
     * Room for the reply. The name is two or three words, but a reasoning
     * model (gpt-oss, gpt-5…) spends tokens thinking first: with a tight cap
     * it stops before writing anything and every suggestion comes back empty.
     */
    private const int MAX_TOKENS = 500;

    public function __construct(
        private readonly LlmChat $chat,
        private readonly ModelRoles $models,
    ) {}

    public function generate(): string
    {
        for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++) {
            $name = self::clean($this->suggest());

            if ($name !== null && ! $this->isTaken($name)) {
                return $name;
            }
        }

        return $this->randomName();
    }

    /**
     * Reduce a model reply to a slug: lowercase first (so capitals
     * are kept as letters, not stripped), drop anything after a dot (a domain
     * the model added), keep [a-z0-9-], and trim dashes. Null when too short.
     */
    public static function clean(?string $reply): ?string
    {
        $name = strtolower(trim((string) $reply, " \t\n\r\0\x0B\"'`"));
        $name = explode('.', $name)[0];
        $name = preg_replace('/\s+/', '-', $name) ?? '';
        $name = preg_replace('/[^a-z0-9-]/', '', $name) ?? '';
        $name = trim(preg_replace('/-{2,}/', '-', $name) ?? '', '-');
        $name = substr($name, 0, self::MAX_LENGTH);

        return strlen($name) >= self::MIN_LENGTH ? rtrim($name, '-') : null;
    }

    private function suggest(): ?string
    {
        try {
            return $this->chat->send(new ChatRequestData(
                model: $this->models->model(LlmRole::Fast),
                messages: [ChatMessageData::user(prompt('agent-slug/generate'))],
                maxTokens: self::MAX_TOKENS,
            ))->content;
        } catch (Throwable $e) {
            Log::warning('Agent slug suggestion failed; falling back to a random name.', ['exception' => $e::class]);

            return null;
        }
    }

    private function randomName(): string
    {
        do {
            $name = 'agent-'.Str::lower(Str::random(8));
        } while ($this->isTaken($name));

        return $name;
    }

    /** A slug may not repeat another agent's slug or external id: both name a chat. */
    private function isTaken(string $name): bool
    {
        return Agent::query()->where('slug', $name)->orWhere('external_id', $name)->exists();
    }
}
