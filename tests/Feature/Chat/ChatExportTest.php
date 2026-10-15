<?php

use App\Http\Chat\HostedChatSession;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Services\Memory\ChatMemory;
use Illuminate\Support\Facades\Bus;
use Illuminate\Testing\TestResponse;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\FakePdfBuilder;

/**
 * A hosted-chat visitor downloads a conversation (or one message of it) as
 * PDF, Markdown or text. The PDF is laid out by WeasyPrint (no browser on the
 * server).
 */
beforeEach(function (): void {
    config(['chatvia.ai.query.rewriter.enabled' => false]);
    Bus::fake();

    $agent = Agent::factory()->create(['is_public' => true]);
    $this->base = "/chat/{$agent->slug}";
    $this->conversationId = $this->postJson("{$this->base}/conversations/create", ['message' => 'Show me **bold** text'])->json('id');

    // The agent's answer, as the turn stores it in the live history.
    app(ChatMemory::class)
        ->withScope(app(HostedChatSession::class)->scope($agent, $this->conversationId))
        ->appendMessage(new MessageData(id: 'answer-1', role: MessageRole::Assistant, content: 'Here is a [link](https://shop.test).', type: MessageData::TYPE_TEXT, timestamp: now()->timestamp));
});

/** The text of a downloaded export. */
function ceDownloaded(TestResponse $response): string
{
    return (string) file_get_contents($response->baseResponse->getFile()->getPathname());
}

it('exports a conversation as a PDF', function (): void {
    Pdf::fake();

    $this->get("{$this->base}/conversations/{$this->conversationId}/export?type=pdf")->assertOk();

    Pdf::assertRespondedWithPdf(fn (FakePdfBuilder $pdf): bool => $pdf->viewName === 'chat.export.pdf'
        && $pdf->isDownload()
        && str_ends_with($pdf->downloadName, '.pdf'));
});

it('exports a conversation as Markdown, message by message', function (): void {
    $response = $this->get("{$this->base}/conversations/{$this->conversationId}/export?type=markdown")
        ->assertOk()
        ->assertDownload();

    expect(ceDownloaded($response))
        ->toContain("Show me **bold** text\n\n---\n\nHere is a [link](https://shop.test).");
});

it('exports a conversation as plain text, with the Markdown rendered away', function (): void {
    $response = $this->get("{$this->base}/conversations/{$this->conversationId}/export?type=text")
        ->assertOk()
        ->assertDownload();

    expect(ceDownloaded($response))
        ->toContain('Show me bold text')
        ->toContain('Here is a link.')
        ->not->toContain('**')
        ->not->toContain('<p>');
});

it('exports a single message of the conversation', function (): void {
    $response = $this->get("{$this->base}/conversations/{$this->conversationId}/export?type=markdown&messageId=answer-1")
        ->assertOk();

    expect(ceDownloaded($response))->toBe("Here is a [link](https://shop.test).\n");
});

it('answers 404 for a message the conversation does not have', function (): void {
    $this->get("{$this->base}/conversations/{$this->conversationId}/export?type=markdown&messageId=no-such-message")
        ->assertNotFound();
});

it('lets WeasyPrint load only data: URLs, so a link in a message cannot make the server fetch it', function (): void {
    expect(config('laravel-pdf.weasyprint.allowed-protocols'))->toBe('data');
});
