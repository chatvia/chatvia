<?php

declare(strict_types=1);

use App\Mcp\Servers\AudioGenerationServer;
use App\Mcp\Tools\Media\GenerateAudioTool;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\GeneratedMediaKind;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\GeneratedMedia;
use Domain\Llm\Services\AudioGeneration;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Llm\FakeSpeaker;
use Tests\Support\Mcp\InternalMcp;

/**
 * generate-audio returns an `audio` artifact the chat shows as a placeholder
 * (the text, cut short, or the attached PDF's name, as its caption), or the
 * reason no file can be made.
 */
beforeEach(function (): void {
    Queue::fake();
    app()->instance(AudioGeneration::class, new AudioGeneration(new FakeSpeaker));
    $this->agent = Agent::factory()->create();
    $this->conversation = Conversation::factory()->create(['agent_id' => $this->agent->id, 'source' => ConversationSource::HostedChat]);
});

function gatCall(array $arguments): array
{
    return InternalMcp::result(AudioGenerationServer::tool(GenerateAudioTool::class, $arguments));
}

it('asks for the audio file and returns a pending audio artifact', function (): void {
    InternalMcp::actAsAgent($this->agent->external_id, $this->conversation->external_id, path: '/mcp/audio-generation');
    $text = Str::repeat('Welcome to our shop. ', 20);

    $result = gatCall(['text' => $text]);
    $media = GeneratedMedia::query()->sole();

    expect($result['content'][0]['text'])->toContain('Do not say where on the screen it will appear')
        ->and($result['structuredContent']['audio']['id'])->toBe($media->external_id)
        ->and($result['structuredContent']['audio']['prompt'])->toBe(Str::limit(Str::trim($text), 200))
        ->and($media->kind)->toBe(GeneratedMediaKind::Audio)
        ->and($media->prompt)->toBe(Str::trim($text));
});

it('passes on why no file can be made', function (): void {
    $widget = Conversation::factory()->create(['agent_id' => $this->agent->id, 'source' => ConversationSource::Widget]);
    InternalMcp::actAsAgent($this->agent->external_id, $widget->external_id, path: '/mcp/audio-generation');

    $result = gatCall(['text' => 'Hello.']);

    expect($result['content'][0]['text'])->toContain('can only be made in the hosted chat')
        ->and(GeneratedMedia::query()->count())->toBe(0);
});

it('reads the attached PDF when asked to, without the text', function (): void {
    Storage::fake(Attachment::DISK);
    Process::fake(['*pdftotext*' => Process::result("Chapter one.\f")]);
    Attachment::factory()->forConversation($this->conversation)->stored()->create(['filename' => 'Guide.pdf']);
    InternalMcp::actAsAgent($this->agent->external_id, $this->conversation->external_id, path: '/mcp/audio-generation');

    $result = gatCall(['source' => 'attachment']);

    expect($result['structuredContent']['audio']['prompt'])->toBe('Guide.pdf')
        ->and(GeneratedMedia::query()->sole()->prompt)->toBe('Chapter one.');
});

it('needs a text unless it reads the attachment', function (): void {
    InternalMcp::actAsAgent($this->agent->external_id, $this->conversation->external_id, path: '/mcp/audio-generation');

    expect(gatCall([])['isError'] ?? false)->toBeTrue()
        ->and(GeneratedMedia::query()->count())->toBe(0);
});
