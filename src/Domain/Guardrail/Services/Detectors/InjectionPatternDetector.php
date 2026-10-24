<?php

declare(strict_types=1);

namespace Domain\Guardrail\Services\Detectors;

use Illuminate\Support\Str;

/**
 * A cheap rules pass for prompt injection hidden in text the agent reads but
 * the visitor did not write (retrieved knowledge chunks, tool and MCP
 * results). No LLM call, so it can run on every chunk and result of every
 * turn.
 *
 * The patterns only match phrasing that has no business in a help article:
 * "ignore all previous instructions", "reveal your system prompt", chat
 * template tokens. Loose phrases such as "you are now" or "system prompt:"
 * are left out on purpose — "You are now logged in" is ordinary help text,
 * and dropping that chunk would quietly make answers worse.
 */
final class InjectionPatternDetector
{
    /** @var array<string, string> name => pattern */
    private const array PATTERNS = [
        'ignore_instructions' => '/\b(?:ignore|disregard|forget|override)\s+(?:all\s+|any\s+)?(?:of\s+)?(?:the\s+|your\s+|my\s+|these\s+)?(?:previous|prior|above|earlier|preceding|system|original)\s+(?:instructions|prompts?|rules|directions|guidelines)\b/iu',
        'reveal_system_prompt' => '/\b(?:reveal|print|repeat|output|show|leak)\s+(?:me\s+)?(?:your|the)\s+(?:system\s+prompt|hidden\s+instructions|initial\s+instructions|original\s+instructions)\b/iu',
        'role_takeover' => '/\byou\s+are\s+now\s+(?:in\s+)?(?:dan\b|developer\s+mode|jailbroken|unrestricted|unfiltered|no\s+longer\s+bound)/iu',
        'chat_template_token' => '/<\|im_start\|>|<\|system\|>|\[\/?INST\]|<<\/?SYS>>/i',
    ];

    /** The name of the first pattern the text matches, or null when it matches none. */
    public function firstMatch(string $text): ?string
    {
        foreach (self::PATTERNS as $name => $pattern) {
            if (Str::isMatch($pattern, $text)) {
                return $name;
            }
        }

        return null;
    }
}
