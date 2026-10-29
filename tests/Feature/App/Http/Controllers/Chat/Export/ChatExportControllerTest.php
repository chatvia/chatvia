<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\MessageData;
use Domain\Conversation\Enums\MessageRole;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\FakePdfBuilder;
use Tests\Support\Conversation\HostedChatVisitor;

/**
 * A hosted-chat visitor downloads a conversation as PDF, Markdown or text.
 * The PDF is laid out by the PDF maker of Settings →
 * Platform (WeasyPrint on the server, or a browser service), which may load
 * nothing.
 */
beforeEach(function (): void {
    $this->agent = Agent::factory()->create(['is_public' => true]);
    $this->conversationId = HostedChatVisitor::startConversation($this->agent, 'Show me **bold** text');
    $this->export = "/chat/{$this->agent->slug}/conversations/{$this->conversationId}/export";

    HostedChatVisitor::memory($this->agent, $this->conversationId)
        ->appendMessage(new MessageData(id: 'answer-1', role: MessageRole::Assistant, content: 'Here is a [link](https://shop.test).', type: MessageData::TYPE_TEXT, timestamp: now()->timestamp));
});

it('exports a conversation as Markdown and deletes the temporary copy once it is sent', function (): void {
    $response = $this->get("{$this->export}?type=markdown")
        ->assertOk()
        ->assertDownload();
    $path = $response->baseResponse->getFile()->getPathname();

    expect(File::get($path))
        ->toContain("Show me **bold** text\n\n---\n\nHere is a [link](https://shop.test).");

    ob_start();
    $response->baseResponse->sendContent();
    ob_end_clean();

    expect(File::exists($path))->toBeFalse();
});

it('keeps every PDF maker from loading anything a message links to', function (): void {
    Pdf::fake();
    HostedChatVisitor::memory($this->agent, $this->conversationId)
        ->appendMessage(new MessageData(id: 'answer-2', role: MessageRole::Assistant, content: 'See ![the chart](http://169.254.169.254/latest/chart.png) and ![a dot](data:image/png;base64,iVBORw0KGgo=).', type: MessageData::TYPE_TEXT, timestamp: now()->timestamp));

    $this->get("{$this->export}?type=pdf")->assertOk();

    Pdf::assertRespondedWithPdf(function (FakePdfBuilder $pdf): bool {
        $html = $pdf->getHtml();

        return Str::contains($html, '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; img-src data:; font-src data:; style-src \'unsafe-inline\'">')
            && ! Str::contains($html, '169.254.169.254')
            && Str::contains($html, 'the chart')
            && Str::contains($html, '<img src="data:image/png;base64,iVBORw0KGgo="');
    });
});
