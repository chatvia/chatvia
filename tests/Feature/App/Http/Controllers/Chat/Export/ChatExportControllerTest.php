<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Enums\MessageRole;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\FakePdfBuilder;
use Tests\Support\Conversation\HostedChatVisitor;

/**
 * A hosted-chat visitor downloads a conversation (or one message of it) as
 * PDF, Markdown or text. The PDF is laid out by the PDF maker of Settings →
 * Platform (WeasyPrint on the server, or a browser service), which may load
 * nothing.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => true]);
    $this->conversationId = HostedChatVisitor::startConversation($this->agent, 'Show me **bold** text');
    $this->export = "/chat/{$this->agent->slug}/conversations/{$this->conversationId}/export";

    // The agent's answer, as the turn stores it in the live history.
    HostedChatVisitor::memory($this->agent, $this->conversationId)
        ->appendMessage(new MessageData(id: 'answer-1', role: MessageRole::Assistant, content: 'Here is a [link](https://shop.test).', type: MessageData::TYPE_TEXT, timestamp: now()->timestamp));
});

/** The text of a downloaded export. */
function cecDownloaded(TestResponse $response): string
{
    return (string) file_get_contents($response->baseResponse->getFile()->getPathname());
}

it('exports a conversation as a PDF', function (): void {
    Pdf::fake();

    $this->get("{$this->export}?type=pdf")->assertOk();

    Pdf::assertRespondedWithPdf(fn (FakePdfBuilder $pdf): bool => $pdf->viewName === 'chat.export.pdf'
        && $pdf->isDownload()
        && Str::endsWith($pdf->downloadName, '.pdf'));
});

it('exports a conversation as Markdown, message by message', function (): void {
    $response = $this->get("{$this->export}?type=markdown")
        ->assertOk()
        ->assertDownload();

    expect(cecDownloaded($response))
        ->toContain("Show me **bold** text\n\n---\n\nHere is a [link](https://shop.test).");
});

it('exports a conversation as plain text, with the Markdown rendered away', function (): void {
    $response = $this->get("{$this->export}?type=text")
        ->assertOk()
        ->assertDownload();

    expect(cecDownloaded($response))
        ->toContain('Show me bold text')
        ->toContain('Here is a link.')
        ->not->toContain('**')
        ->not->toContain('<p>');
});

it('deletes the temporary copy of a text export once it is sent', function (string $type): void {
    $response = $this->get("{$this->export}?type={$type}")->assertOk();
    $path = $response->baseResponse->getFile()->getPathname();

    expect(file_exists($path))->toBeTrue();

    ob_start();
    $response->baseResponse->sendContent();
    ob_end_clean();

    expect(file_exists($path))->toBeFalse();
})->with(['markdown', 'text']);

it('exports a single message of the conversation', function (): void {
    $response = $this->get("{$this->export}?type=markdown&messageId=answer-1")
        ->assertOk();

    expect(cecDownloaded($response))->toBe("Here is a [link](https://shop.test).\n");
});

it('answers 404 for a message the conversation does not have', function (): void {
    $this->get("{$this->export}?type=markdown&messageId=no-such-message")
        ->assertNotFound();
});

it('keeps every PDF maker from loading anything a message links to', function (): void {
    Pdf::fake();
    HostedChatVisitor::memory($this->agent, $this->conversationId)
        ->appendMessage(new MessageData(id: 'answer-2', role: MessageRole::Assistant, content: 'See ![the chart](http://169.254.169.254/latest/chart.png) and ![a dot](data:image/png;base64,iVBORw0KGgo=).', type: MessageData::TYPE_TEXT, timestamp: now()->timestamp));

    $this->get("{$this->export}?type=pdf")->assertOk();

    // A browser maker obeys the page's policy; a remote image is left out.
    Pdf::assertRespondedWithPdf(function (FakePdfBuilder $pdf): bool {
        $html = $pdf->getHtml();

        return Str::contains($html, '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src data:; font-src data:; style-src \'unsafe-inline\'">')
            && ! Str::contains($html, '169.254.169.254')
            && Str::contains($html, 'the chart')
            && Str::contains($html, '<img src="data:image/png;base64,iVBORw0KGgo="');
    });
});
