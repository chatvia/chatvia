<?php

use Spatie\LaravelPdf\Drivers\PdfDriver;
use Spatie\LaravelPdf\PdfOptions;

/**
 * The DPA download starts a headless browser per render, so it is throttled
 * and identical requests are served from the PDF cache.
 */
beforeEach(function (): void {
    $this->driver = new class implements PdfDriver
    {
        public int $renders = 0;

        public function generatePdf(string $html, ?string $headerHtml, ?string $footerHtml, PdfOptions $options): string
        {
            $this->renders++;

            return '%PDF-fake-'.$this->renders;
        }

        public function savePdf(string $html, ?string $headerHtml, ?string $footerHtml, PdfOptions $options, string $path): void {}
    };

    app()->instance(PdfDriver::class, $this->driver);
});

it('renders the PDF once for identical requests', function (): void {
    $first = $this->get(route('dpa.download', ['company_name' => 'Acme GmbH']));
    $second = $this->get(route('dpa.download', ['company_name' => 'Acme GmbH']));

    $first->assertOk();
    $second->assertOk();

    expect($this->driver->renders)->toBe(1)
        ->and($second->getContent())->toBe($first->getContent());
});

it('renders a new PDF when the input changes', function (): void {
    $this->get(route('dpa.download', ['company_name' => 'Acme GmbH']))->assertOk();
    $this->get(route('dpa.download', ['company_name' => 'Other Ltd']))->assertOk();

    expect($this->driver->renders)->toBe(2);
});

it('throttles repeated downloads from one visitor', function (): void {
    foreach (range(1, 10) as $i) {
        $this->get(route('dpa.download', ['company_name' => "Company {$i}"]))->assertOk();
    }

    $this->get(route('dpa.download', ['company_name' => 'One too many']))->assertTooManyRequests();
});
