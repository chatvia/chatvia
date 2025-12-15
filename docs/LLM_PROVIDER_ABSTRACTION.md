# LLM Provider Abstraction Layer

This document explains the LLM provider abstraction layer that allows easy switching between OpenAI-compatible API providers like OpenRouter and Groq.

## Overview

The application uses an interface-based abstraction layer for LLM providers. This design allows you to:
- Easily switch between different LLM providers (OpenRouter, Groq, etc.)
- Add new providers without modifying existing code
- Handle provider-specific features (like cost tracking)
- Maintain consistent API usage across the application

## Architecture

### Components

1. **`LLMProviderInterface`** (`app/Contracts/LLMProviderInterface.php`)
   - Defines the contract that all LLM providers must implement
   - Methods: `stream()`, `completion()`, `headers()`, `getGenerationCost()`, `supportsCostTracking()`

2. **`AbstractLLMProvider`** (`app/Services/Infrastructure/LLM/AbstractLLMProvider.php`)
   - Abstract base class providing common functionality
   - Implements default behavior for streaming and completion requests
   - Handles OpenAI-compatible API requests

3. **Concrete Implementations**
   - `OpenRouter` - OpenRouter provider with cost tracking support
   - `Groq` - Groq provider without cost tracking

## Configuration

### Environment Variables

Add to your `.env` file:

```env
# Choose your LLM provider: openrouter or groq
LLM_PROVIDER=openrouter

# OpenRouter Configuration
OPENROUTER_API_KEY=your_openrouter_api_key
OPENROUTER_BASE_URL=https://openrouter.ai/api/v1
OPENROUTER_COMPLETION_ENDPOINT=https://openrouter.ai/api/v1/chat/completions

# Groq Configuration
GROQ_API_KEY=your_groq_api_key
GROQ_BASE_URL=https://api.groq.com/openai/v1
GROQ_COMPLETION_ENDPOINT=https://api.groq.com/openai/v1/chat/completions
```

### Switching Providers

To switch between providers, simply change the `LLM_PROVIDER` environment variable:

```env
# Use OpenRouter
LLM_PROVIDER=openrouter

# Or use Groq
LLM_PROVIDER=groq
```

The application will automatically use the correct provider based on this configuration.

## Usage

### Dependency Injection

Always use dependency injection with the `LLMProviderInterface`:

```php
use App\Contracts\LLMProviderInterface;

class YourService
{
    public function __construct(
        private LLMProviderInterface $llmProvider
    ) {}
    
    public function doSomething()
    {
        $response = $this->llmProvider->completion([
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'user', 'content' => 'Hello!']
            ]
        ]);
    }
}
```

### Streaming Requests

```php
$response = $this->llmProvider->stream(
    $model,
    $messages,
    $settings
);
```

### Regular Completion Requests

```php
$response = $this->llmProvider->completion([
    'model' => 'model-name',
    'messages' => $messages,
    // ... other parameters
]);
```

### Cost Tracking

Only OpenRouter supports cost tracking. Check before using:

```php
if ($this->llmProvider->supportsCostTracking()) {
    $costData = $this->llmProvider->getGenerationCost($generationId);
    // $costData contains: tokens_prompt, tokens_completion, total_cost, etc.
}
```

## Provider-Specific Features

### OpenRouter

- **Cost Tracking**: ✅ Supported via `getGenerationCost()`
- **Model Formatting**: Adds `:nitro` suffix to model names
- **Features**:
  - Token usage tracking
  - Cost calculation per request
  - Generation ID tracking

### Groq

- **Cost Tracking**: ❌ Not supported
- **Model Formatting**: Uses model names as-is
- **Features**:
  - Fast inference
  - OpenAI-compatible API

## Adding a New Provider

To add a new OpenAI-compatible provider:

1. Create a new class extending `AbstractLLMProvider`:

```php
<?php

namespace App\Services\Infrastructure\LLM;

class YourProvider extends AbstractLLMProvider
{
    public function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->getApiKey(),
            'Content-Type' => 'application/json',
        ];
    }

    public function getCompletionEndpoint(): string
    {
        return config('services.yourprovider.completion_endpoint');
    }

    protected function getApiKey(): string
    {
        return config('services.yourprovider.api_key');
    }

    protected function getBaseUrl(): string
    {
        return config('services.yourprovider.base_url');
    }
    
    // Override if you need custom model formatting
    protected function formatModel(string $model): string
    {
        return $model;
    }
    
    // Override if you support cost tracking
    public function supportsCostTracking(): bool
    {
        return true;
    }
    
    public function getGenerationCost(string $generationId): ?array
    {
        // Implement your cost tracking logic
    }
}
```

2. Add configuration to `config/services.php`:

```php
'yourprovider' => [
    'api_key' => env('YOURPROVIDER_API_KEY'),
    'base_url' => env('YOURPROVIDER_BASE_URL'),
    'completion_endpoint' => env('YOURPROVIDER_COMPLETION_ENDPOINT'),
],
```

3. Update the provider binding in `app/Providers/AppServiceProvider.php`:

```php
$this->app->bind(LLMProviderInterface::class, function ($app) {
    $provider = config('services.llm_provider', 'openrouter');

    return match ($provider) {
        'groq' => $app->make(Groq::class),
        'openrouter' => $app->make(OpenRouter::class),
        'yourprovider' => $app->make(YourProvider::class),
        default => $app->make(OpenRouter::class),
    };
});
```

## Files Updated

The following files use the LLM provider interface:

- `app/Services/Chat/StreamHandler.php` - Chat streaming
- `app/Services/Memory/ConversationManager.php` - Conversation title generation
- `app/Services/Subdomain.php` - Subdomain generation
- `app/Jobs/StoreChatMessageJob.php` - Message storage with cost tracking

## Benefits

1. **Flexibility**: Switch providers without code changes
2. **Maintainability**: Single point of configuration
3. **Testability**: Easy to mock the interface for testing
4. **Scalability**: Add new providers easily
5. **Type Safety**: Interface ensures consistent implementation

## Notes

- All providers must be OpenAI-compatible (same request/response format)
- Cost tracking is optional and provider-specific
- Model name formatting can be customized per provider
- The abstract class handles common functionality to reduce duplication

