<?php

declare(strict_types=1);

use App\Mcp\Servers\ImageGenerationServer;
use App\Mcp\Tools\Media\GenerateImageTool;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\GeneratedMedia;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\Llm\FakeImageGenerator;
use Tests\Support\Mcp\InternalMcp;

/**
 * generate-image returns an `image` artifact the chat shows as a placeholder,
 * or the reason no picture can be made, and refuses a call outside an
 * agent's own conversation.
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

    expect($result['content'][0]['text'])->toContain('will appear under your reply')
        ->and($result['structuredContent']['image'])->toBe(['id' => $media->external_id, 'status' => 'pending', 'prompt' => 'A lighthouse at dusk'])
        ->and($media->shape)->toBe('landscape');
});

it('passes on why no picture can be made', function (): void {
    FakeImageGenerator::install(enabled: false);
    InternalMcp::actAsAgent($this->agent->external_id, $this->conversation->external_id, path: '/mcp/image-generation');

    $result = gitCall(['prompt' => 'A fox']);

    expect($result)->not->toHaveKey('structuredContent')
        ->and($result['content'][0]['text'])->toContain('not set up on this server')
        ->and(GeneratedMedia::query()->count())->toBe(0);
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

it('refuses a description that is too long or a shape it does not know', function (array $arguments): void {
    InternalMcp::actAsAgent($this->agent->external_id, $this->conversation->external_id, path: '/mcp/image-generation');

    expect(gitCall($arguments)['isError'] ?? false)->toBeTrue()
        ->and(GeneratedMedia::query()->count())->toBe(0);
})->with([
    'too long' => [['prompt' => Str::repeat('a', GenerateImageTool::MAX_PROMPT + 1)]],
    'unknown shape' => [['prompt' => 'A fox', 'shape' => 'round']],
]);
