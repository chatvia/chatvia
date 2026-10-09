<?php

namespace Domain\Agent\Services;

use Domain\Agent\Models\Agent;
use Domain\Llm\Data\Chat\ChatMessageData;
use Domain\Llm\Data\Chat\ChatRequestData;
use Domain\Llm\Services\LlmChat;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Suggests a free, subdomain-safe name for an agent's Hosted Chat: the model
 * proposes a short playful name (`chatvia.ai.models.subdomain`), which is
 * cleaned to lowercase letters, digits and inner dashes. A taken or unusable
 * suggestion is retried a few times; after that — or when the model is
 * unreachable — a random `agent-…` name is used, so the button always gets an
 * answer.
 */
class SubdomainGenerator
{
    private const int ATTEMPTS = 3;

    private const int MIN_LENGTH = 3;

    private const int MAX_LENGTH = 63;

    /** Labels the Hosted Chat host routing treats as its own, never an agent. */
    private const array RESERVED = ['www'];

    public function __construct(
        private readonly LlmChat $chat,
    ) {}

    public function generate(): string
    {
        for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++) {
            $name = self::clean($this->suggest());

            if ($name !== null && ! self::isTaken($name)) {
                return $name;
            }
        }

        return $this->randomName();
    }

    /**
     * Reduce a model reply to a subdomain label: lowercase first (so capitals
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
                model: (string) config('chatvia.ai.models.subdomain'),
                messages: [ChatMessageData::user(prompt('subdomain/generate'))],
                maxTokens: 30,
            ))->content;
        } catch (Throwable $e) {
            Log::warning('Subdomain suggestion failed; falling back to a random name.', ['exception' => $e::class]);

            return null;
        }
    }

    private function randomName(): string
    {
        do {
            $name = 'agent-'.Str::lower(Str::random(8));
        } while (self::isTaken($name));

        return $name;
    }

    private static function isTaken(string $name): bool
    {
        return in_array($name, self::RESERVED, true) || Agent::query()->where('sub_domain', $name)->exists();
    }
}
