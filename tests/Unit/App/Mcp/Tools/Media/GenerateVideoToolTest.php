<?php

declare(strict_types=1);

use App\Mcp\Servers\VideoGenerationServer;
use App\Mcp\Tools\Media\GenerateVideoTool;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\GeneratedMediaKind;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\GeneratedMedia;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\Llm\FakeVideoGenerator;
use Tests\Support\Mcp\InternalMcp;

/**
 * generate-video returns a `video` artifact the chat shows as a placeholder
 * while the video is made (minutes), or the reason no video can be made,
 * and refuses a call outside an agent's own conversation.
 */
beforeEach(function (): void {
    Queue::fake();
    FakeVideoGenerator::install();
    $this->agent = Agent::factory()->create();
    $this->conversation = Conversation::factory()->create(['agent_id' => $this->agent->id, 'source' => ConversationSource::HostedChat]);
});

function gvtCall(array $arguments): array
{
    return InternalMcp::result(VideoGenerationServer::tool(GenerateVideoTool::class, $arguments));
}

it('asks for the video and returns a pending video artifact', function (): void {
    InternalMcp::actAsAgent($this->agent->external_id, $this->conversation->external_id, path: '/mcp/video-generation');

    $result = gvtCall(['prompt' => "  A lighthouse\n in a storm ", 'shape' => 'portrait']);
    $media = GeneratedMedia::query()->sole();

    expect($result['content'][0]['text'])->toContain('may take a few minutes')
        ->and($result['content'][0]['text'])->toContain('Do not say where on the screen it will appear')
        ->and($result['structuredContent']['video'])->toBe(['id' => $media->external_id, 'status' => 'pending', 'prompt' => 'A lighthouse in a storm'])
        ->and($media->kind)->toBe(GeneratedMediaKind::Video)
        ->and($media->shape)->toBe('portrait');
});

it('passes on why no video can be made', function (): void {
    FakeVideoGenerator::install(enabled: false);
    InternalMcp::actAsAgent($this->agent->external_id, $this->conversation->external_id, path: '/mcp/video-generation');

    $result = gvtCall(['prompt' => 'A fox']);

    expect($result)->not->toHaveKey('structuredContent')
        ->and($result['content'][0]['text'])->toContain('Making videos is not set up on this server')
        ->and(GeneratedMedia::query()->count())->toBe(0);
});

it('refuses a call without the agent\'s own conversation', function (): void {
    InternalMcp::actAsAgent($this->agent->external_id, Conversation::factory()->create()->external_id, path: '/mcp/video-generation');

    expect(gvtCall(['prompt' => 'A fox'])['isError'] ?? false)->toBeTrue()
        ->and(GeneratedMedia::query()->count())->toBe(0);
});

it('refuses a description that is too long or a shape it does not know', function (array $arguments): void {
    InternalMcp::actAsAgent($this->agent->external_id, $this->conversation->external_id, path: '/mcp/video-generation');

    expect(gvtCall($arguments)['isError'] ?? false)->toBeTrue()
        ->and(GeneratedMedia::query()->count())->toBe(0);
})->with([
    'too long' => [['prompt' => Str::repeat('a', GenerateVideoTool::MAX_PROMPT + 1)]],
    'square' => [['prompt' => 'A fox', 'shape' => 'square']],
]);
