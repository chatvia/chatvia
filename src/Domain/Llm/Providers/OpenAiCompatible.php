<?php

declare(strict_types=1);

namespace Domain\Llm\Providers;

use Domain\Llm\AbstractLlmProvider;

/**
 * Chat completions through any OpenAI-compatible API, with no changes to the
 * request: OpenAI itself, Gemini's OpenAI endpoint, Groq, and servers an
 * admin runs (vLLM, Ollama, LM Studio, ...).
 */
class OpenAiCompatible extends AbstractLlmProvider {}
