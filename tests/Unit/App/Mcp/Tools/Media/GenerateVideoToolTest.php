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
use Tests\Support\Llm\FakeVideoGenerator;
use Tests\Support\Mcp\InternalMcp;

/**
 * generate-video returns a `video` artifact the chat shows as a placeholder
 * while the video is made (minutes).
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
    expect($result['structuredContent']['video'])->toBe(['id' => $media->external_id, 'status' => 'pending', 'prompt' => 'A lighthouse in a storm', 'model_name' => null])
        ->and($media->kind)->toBe(GeneratedMediaKind::Video)
        ->and($media->metadata['shape'] ?? null)->toBe('portrait');
});
