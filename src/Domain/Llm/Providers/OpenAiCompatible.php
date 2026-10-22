<?php

declare(strict_types=1);

namespace Domain\Llm\Providers;

use Domain\Llm\AbstractLlmProvider;
use Domain\Llm\Models\Llm;

/**
 * Chat completions through any OpenAI-compatible API: Gemini's OpenAI
 * endpoint, Groq, and servers an admin runs (vLLM, Ollama, LM Studio, ...).
 * The request goes as it is: the reasoning effort, when sent, is already a
 * value the model takes ({@see Llm::effortValue()}).
 */
class OpenAiCompatible extends AbstractLlmProvider {}
