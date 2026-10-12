<?php

use Domain\Agent\Models\Agent;
use Illuminate\Support\Facades\Bus;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\FakePdfBuilder;

/**
 * A hosted-chat visitor downloads a conversation as PDF, Markdown or text. The
 * PDF is laid out by WeasyPrint (no browser on the server).
 */
beforeEach(function (): void {
    config(['cache.stores.chat' => ['driver' => 'array'], 'chatvia.ai.query.rewriter.enabled' => false]);
    Bus::fake();

    $agent = Agent::factory()->create(['is_public' => true]);
    $this->base = "/chat/{$agent->slug}";
    $this->conversationId = $this->postJson("{$this->base}/conversations/create", ['message' => 'Where is my order?'])->json('id');
});

it('makes PDFs with WeasyPrint', function (): void {
    expect(config('laravel-pdf.driver'))->toBe('weasyprint');
});

it('exports a conversation as a PDF', function (): void {
    Pdf::fake();

    $this->get("{$this->base}/conversations/{$this->conversationId}/export?type=pdf")->assertOk();

    Pdf::assertRespondedWithPdf(fn (FakePdfBuilder $pdf): bool => $pdf->viewName === 'chat.export.pdf'
        && $pdf->isDownload()
        && str_ends_with($pdf->downloadName, '.pdf'));
});

it('exports a conversation as Markdown', function (): void {
    $this->get("{$this->base}/conversations/{$this->conversationId}/export?type=markdown")
        ->assertOk()
        ->assertDownload();
});

it('exports a conversation as plain text, with the Markdown rendered away', function (): void {
    $this->postJson("{$this->base}/conversations/create", ['message' => 'Show me **bold** text']);

    $response = $this->get("{$this->base}/conversations/{$this->conversationId}/export?type=text")
        ->assertOk()
        ->assertDownload();

    expect(file_get_contents($response->baseResponse->getFile()->getPathname()))
        ->toContain('Where is my order?')
        ->not->toContain('<p>');
});

it('never lets WeasyPrint fetch anything but data: URLs', function (): void {
    expect(config('laravel-pdf.weasyprint.allowed-protocols'))->toBe('data');
});
