<?php

namespace Domain\Agents\Enums;

/**
 * Inference providers we can route LLM calls to. The stored value is the
 * provider's lowercase slug; the matching PostgreSQL enum in the `llms`
 * migration must list the same set in the same order.
 *
 * Adding a provider means:
 *   1. Add a case here.
 *   2. Extend the migration's `enum('provider', [...])` list + migrate:fresh.
 *   3. Ship the matching SDK adapter that knows the provider's wire format.
 */
enum LlmProviderEnum: string
{
    case SCALEWAY = 'scaleway';
    case OPENAI = 'openai';
    case ANTHROPIC = 'anthropic';
    case MISTRAL = 'mistral';
    case GOOGLE = 'google';
}
