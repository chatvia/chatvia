<?php

declare(strict_types=1);

use App\Mcp\Servers\ImageGenerationServer;
use App\Mcp\Tools\Media\GenerateImageTool;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\GeneratedMedia;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Llm\FakeImageGenerator;
use Tests\Support\Mcp\InternalMcp;

/**
 * generate-image returns an `image` artifact the chat shows as a placeholder,
 * and refuses a call outside an agent's own conversation.
 */
beforeEach(function (): void {
    Queue::fake();
    FakeImageGenerator::install();
    $this->agent = Agent::factory()->create();
    $this->conversation = Conversation::factory()->create(['agent_id' => $this->agent->id, 'source' => ConversationSource::HostedChat]);
});

function gitCall(array $arguments): array
{
    return InternalMcp::result(ImageGenerationServer::tool(GenerateImageTool::class, $arguments));
}

it('asks for the picture and returns a pending image artifact', function (): void {
    InternalMcp::actAsAgent($this->agent->external_id, $this->conversation->external_id, path: '/mcp/image-generation');

    $result = gitCall(['prompt' => "  A lighthouse\n at dusk ", 'shape' => 'landscape']);

    $media = GeneratedMedia::query()->sole();
    expect($result['structuredContent']['image'])->toBe(['id' => $media->external_id, 'status' => 'pending', 'prompt' => 'A lighthouse at dusk', 'model_name' => null])
        ->and($media->metadata)->toEqual(['shape' => 'landscape', 'model' => 'fal-ai/flux-2']);
});

it('refuses a call without the agent\'s own conversation', function (?string $conversation): void {
    InternalMcp::actAsAgent($this->agent->external_id, $conversation, path: '/mcp/image-generation');

    $result = gitCall(['prompt' => 'A fox']);

    expect($result['isError'] ?? false)->toBeTrue()
        ->and(GeneratedMedia::query()->count())->toBe(0);
})->with([
    'no conversation' => [null],
    'another agent\'s conversation' => [fn (): string => Conversation::factory()->create()->external_id],
]);
