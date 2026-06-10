<?php

namespace Domain\Integration\Mcp\Services\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('people_search')]
#[Description('Find structured public profile data about a person — role, company, background, public profiles. Use when the question is about who someone is; use web_search for news stories that merely mention a person.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld]
class PerplexityPeopleSearchTool extends PerplexityAgentTool
{
    protected function toolType(): string
    {
        return 'people_search';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Who to look up, with any disambiguating context, e.g. "Lisa Su, CEO of AMD" or "Jane Doe, robotics researcher at ETH".')
                ->required(),
        ];
    }
}
