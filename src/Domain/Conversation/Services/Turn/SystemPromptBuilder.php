<?php

namespace Domain\Conversation\Services\Turn;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Services\ChatContext;
use Domain\Guardrail\Services\GuardrailService;
use Illuminate\Support\Str;
use Locale;

/**
 * Assembles what the LLM receives for a turn: the system prompt (the
 * guardrails' leak marker, the agent's instructions, the grounding rules, who
 * the visitor is on protected agents, and the business's contact details),
 * then the recent history, then the visitor's message with any retrieved
 * reference material. A voice call gets its own instructions
 * ({@see voicePrompt()}).
 *
 * The leak marker comes first, so a model tricked into dumping its prompt
 * repeats it and the output guard can replace the answer
 * ({@see GuardrailService::leakMarker()}).
 *
 * Forms are not listed here: the agent discovers them through the Forms MCP
 * server (find-forms → render-form), so a form only enters the context when
 * the model searches for one; the web-only channel gate lives in those tools.
 */
class SystemPromptBuilder
{
    public function __construct(
        private readonly GuardrailService $guardrails,
    ) {}

    /**
     * The OpenAI-shaped message list for the turn. When the history already
     * ends with the visitor's message it is replaced by the enriched one.
     *
     * @return array<int, array<string, mixed>>
     */
    public function messagesFor(ChatContext $context): array
    {
        $history = $context->llmMessages;
        $last = end($history);

        if ($last && $last['role'] === MessageRole::User->value) {
            array_pop($history);
        }

        return [
            ['role' => 'system', 'content' => $this->systemPrompt($context->requestData, $context->queryLanguage)],
            ...$history,
            ['role' => MessageRole::User->value, 'content' => $context->payload->message_for_llm],
        ];
    }

    /**
     * `$language` is the ISO 639-1 code of the visitor's message when one was
     * detected ({@see ChatContext::$queryLanguage}):
     * the grounding rules then name that language, so reference material
     * written in another language cannot drag the answer into it. With none
     * detected, they name the browser's language for a message too short to
     * show its own (a lone "summary" next to Danish sources got Danish).
     */
    public function systemPrompt(StreamRequestData $data, ?string $language = null): string
    {
        $agent = $data->agent;
        $marker = $data->guardrailsEnabled ? $this->guardrails->leakMarker($agent) : null;

        return $this->withCallerAndBusiness(
            ($marker !== null ? Str::trim(prompt('guardrails/leak-marker', ['marker' => $marker]))."\n\n" : '')
                .Str::rtrim($agent->resolveSystemInstructions())."\n\n".prompt('knowledge/grounding', [
                    'language' => $language !== null ? Locale::getDisplayLanguage($language, 'en') : null,
                    'browserLanguage' => $this->browserLanguage($data->locale),
                ]),
            $agent,
            $data->chatterName,
        );
    }

    /**
     * The instructions of a voice call: the agent's own instructions, the
     * rules of speaking (short, no markdown, search the knowledge before
     * answering — a call has no reference material pasted in), who the
     * visitor is and the business's details, then the messages typed before
     * the call so it carries on from them.
     *
     * @param  array<int, array{role: string, content: string}>  $history
     */
    public function voicePrompt(Agent $agent, ?string $chatterName, array $history): string
    {
        $prompt = $this->withCallerAndBusiness(
            Str::rtrim($agent->resolveSystemInstructions())."\n\n".prompt('voice/system'),
            $agent,
            $chatterName,
        );

        if ($history !== []) {
            $prompt .= "\n\n".prompt('voice/history', ['messages' => $history]);
        }

        return $prompt;
    }

    /** The prompt followed by who the visitor is (protected agents) and the business profile. */
    private function withCallerAndBusiness(string $prompt, Agent $agent, ?string $chatterName): string
    {
        // Visitors of a protected agent had to sign in, so the model may
        // address them by name. Public agents serve anonymous visitors — the
        // name is left out there even when a chatter happens to be signed in.
        if (! $agent->is_public && filled($chatterName)) {
            $prompt .= "\n\n".prompt('chatter/identity', ['name' => $chatterName]);
        }

        $business = array_filter($agent->settings('business')->toArray(), filled(...));

        if ($business !== []) {
            $prompt .= "\n\n".prompt('business/profile', [
                'email' => $business['email'] ?? null,
                'phone' => $business['phone'] ?? null,
                'website' => $business['website'] ?? null,
                'address' => $business['address'] ?? null,
                'opening_hours' => $business['opening_hours'] ?? null,
            ]);
        }

        return $prompt;
    }

    /**
     * The English name of the first language in the browser's
     * `Accept-Language` (`da-DK,da;q=0.9` → Danish), or null without one.
     */
    private function browserLanguage(?string $locale): ?string
    {
        $tag = Str::of($locale ?? '')->before(',')->before(';')->trim()->toString();

        if (! Str::isMatch('/^[a-z]{2,3}([-_]|$)/i', $tag)) {
            return null;
        }

        $code = Locale::getPrimaryLanguage($tag);
        $name = Locale::getDisplayLanguage($code, 'en');

        return $name !== $code ? $name : null;
    }
}
