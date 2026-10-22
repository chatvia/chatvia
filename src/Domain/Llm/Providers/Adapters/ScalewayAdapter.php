<?php

declare(strict_types=1);

namespace Domain\Llm\Providers\Adapters;

use Domain\Llm\Contracts\LlmProviderInterface;
use Domain\Llm\Enums\LlmProviderKind;
use Domain\Llm\Models\LlmProvider;
use Domain\Llm\Providers\Scaleway;

/**
 * Scaleway's Generative APIs: open models hosted in Europe. Its model list
 * carries ids only, so what each model can do comes from the table below.
 * Its key and address are the Scaleway AI account's (Settings → AI
 * providers), which also runs Qwen3 search and speech to text. An address of
 * the form `https://api.scaleway.ai/{project-id}/v1` bills a given project.
 */
final class ScalewayAdapter extends OpenAiCompatibleAdapter
{
    /**
     * From Scaleway's supported-models page (October 2026): image input and
     * tool calling, and the models that do not chat. A model missing here is still listed, with what its id says.
     */
    private const array KNOWN_MODELS = [
        'deepseek-r1-distill-llama-70b' => ['function_calling' => true],
        'deepseek-r1-distill-llama-8b' => ['function_calling' => true],
        'deepseek-v4-flash-0731' => ['function_calling' => true],
        'devstral-2-123b-instruct-2512' => ['function_calling' => true],
        'devstral-small-2505' => ['function_calling' => true],
        'gemma-3-27b-it' => ['vision' => true],
        'gemma-4-26b-a4b-it' => ['vision' => true, 'function_calling' => true],
        'gemma-4-31b-it' => ['vision' => true, 'function_calling' => true],
        'glm-5.2' => ['function_calling' => true],
        'gpt-oss-120b' => ['function_calling' => true],
        'holo2-30b-a3b' => ['vision' => true, 'function_calling' => false],
        'llama-3-70b-instruct' => ['function_calling' => false],
        'llama-3.1-70b-instruct' => ['function_calling' => true],
        'llama-3.1-8b-instruct' => ['function_calling' => true],
        'llama-3.1-nemotron-70b-instruct' => ['function_calling' => true],
        'llama-3.3-70b-instruct' => ['function_calling' => true],
        'magistral-small-2506' => ['function_calling' => true],
        'mistral-7b-instruct-v0.3' => ['function_calling' => true],
        'mistral-large-3-675b-instruct-2512' => ['vision' => true, 'function_calling' => true],
        'mistral-medium-3.5-128b' => ['vision' => true, 'function_calling' => true],
        'mistral-nemo-instruct-2407' => ['function_calling' => true],
        'mistral-small-24b-instruct-2501' => ['function_calling' => true],
        'mistral-small-3.1-24b-instruct-2503' => ['vision' => true, 'function_calling' => true],
        'mistral-small-3.2-24b-instruct-2506' => ['vision' => true, 'function_calling' => true],
        'mixtral-8x7b-instruct-v0.1' => ['function_calling' => false],
        'molmo-72b-0924' => ['vision' => true, 'function_calling' => false],
        'pixtral-12b-2409' => ['vision' => true, 'function_calling' => true],
        'qwen2.5-coder-32b-instruct' => ['function_calling' => true],
        'qwen3-235b-a22b-instruct-2507' => ['function_calling' => true],
        'qwen3-coder-30b-a3b-instruct' => ['function_calling' => true],
        'qwen3.5-122b-a10b' => ['vision' => true],
        'qwen3.5-35b-a3b' => ['vision' => true],
        'qwen3.5-397b-a17b' => ['vision' => true, 'function_calling' => true],
        'qwen3.6-35b-a3b' => ['vision' => true, 'function_calling' => true],
        'qwen3.8-27b' => ['vision' => true, 'function_calling' => true],
        'voxtral-small-24b-2507' => ['function_calling' => true],
        // Not chat models: search vectors and speech to text.
        'bge-multilingual-gemma2' => ['chat' => false],
        'qwen3-embedding-8b' => ['chat' => false],
        'sentence-t5-xxl' => ['chat' => false],
        'whisper-large-v3' => ['chat' => false],
    ];

    public function kind(): LlmProviderKind
    {
        return LlmProviderKind::Scaleway;
    }

    public function label(): string
    {
        return 'Scaleway AI';
    }

    public function description(): string
    {
        return 'Open models hosted in Europe. The same key also covers search and speech to text.';
    }

    public function defaultBaseUrl(): string
    {
        return 'https://api.scaleway.ai/v1';
    }

    public function keysUrl(): string
    {
        return 'https://console.scaleway.com/iam/api-keys';
    }

    public function client(LlmProvider $provider): LlmProviderInterface
    {
        return new Scaleway($provider, $this->baseUrl($provider), $this->apiKey($provider) ?? '');
    }

    protected function knownModels(): array
    {
        return self::KNOWN_MODELS;
    }
}
