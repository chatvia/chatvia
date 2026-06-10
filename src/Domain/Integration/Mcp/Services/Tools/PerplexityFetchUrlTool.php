<?php

namespace Domain\Integration\Mcp\Services\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('fetch_url')]
#[Description('Read the full contents of a specific web page. Use when you already have the URL and need what is on that page — optionally with a question to answer from it. Not a search; use web_search to find pages.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld]
class PerplexityFetchUrlTool extends PerplexityAgentTool
{
    protected function toolType(): string
    {
        return 'fetch_url';
    }

    protected function instructions(): ?string
    {
        return 'Fetch the page at the URL the user provides. If they ask a question about it, answer from the page contents; otherwise return a faithful summary of what the page says.';
    }

    protected function rules(): array
    {
        return [
            'url' => ['required', 'string', 'url', 'max:2000'],
            'question' => ['sometimes', 'string', 'max:500'],
        ];
    }

    protected function validationMessages(): array
    {
        return [
            'url.required' => 'You must provide the URL of the page to fetch.',
            'url.url' => 'The url must be a valid absolute URL (including https://).',
        ];
    }

    protected function inputFor(array $validated): string
    {
        $input = 'Fetch this page: '.$validated['url'];
        if (! empty($validated['question'])) {
            $input .= "\n\nThen answer: ".$validated['question'];
        }

        return $input;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'url' => $schema->string()
                ->description('The absolute URL of the page to read, e.g. "https://example.com/pricing".')
                ->required(),
            'question' => $schema->string()
                ->description('Optional: what to answer or extract from the page. Omit to get the page contents.'),
        ];
    }
}
