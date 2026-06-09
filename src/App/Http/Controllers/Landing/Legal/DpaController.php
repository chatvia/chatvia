<?php

namespace App\Http\Controllers\Landing\Legal;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Support\Browsershot\BrowsershotConfigurator;

class DpaController
{
    public function __construct(
        private readonly BrowsershotConfigurator $browsershotConfig,
    ) {}

    /** Self-service "Generate your DPA" page. */
    public function show(): View
    {
        return view('landing.legal.dpa');
    }

    /** Render a customer-specific DPA (both parties filled in) as a downloadable PDF. */
    public function download(Request $request): PdfBuilder
    {
        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'registered_address' => ['nullable', 'string', 'max:1000'],
            'country' => ['nullable', 'string', 'max:120'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'signatory_name' => ['nullable', 'string', 'max:255'],
            'signatory_title' => ['nullable', 'string', 'max:255'],
        ]);

        $filename = 'Chatvia-DPA-'.Str::slug($validated['company_name']);

        return Pdf::withBrowsershot(fn (Browsershot $browsershot): Browsershot => $this->browsershotConfig->configure($browsershot))
            ->view('pdf.dpa', [
                'companyName' => $validated['company_name'],
                'registrationNumber' => ($validated['registration_number'] ?? null) ?: null,
                'registeredAddress' => ($validated['registered_address'] ?? null) ?: null,
                'country' => ($validated['country'] ?? null) ?: null,
                'contactEmail' => ($validated['contact_email'] ?? null) ?: null,
                'signatoryName' => ($validated['signatory_name'] ?? null) ?: null,
                'signatoryTitle' => ($validated['signatory_title'] ?? null) ?: null,
                'bodyHtml' => $this->dpaBodyHtml(),
                'generatedAt' => now()->isoFormat('D MMMM YYYY'),
            ])
            ->download($filename.'.pdf');
    }

    /**
     * The DPA clauses and annexes (everything from §1 to before the internal
     * control-mapping appendix), rendered to HTML. The party recital is omitted
     * here — the PDF cover supplies the filled-in parties block instead.
     */
    private function dpaBodyHtml(): string
    {
        $markdown = (string) file_get_contents(base_path('docs/compliance/gdpr/data-processing-agreement.md'));

        $start = strpos($markdown, '## 1. Definitions');
        $end = strpos($markdown, '## Mapping');

        $body = $start !== false && $end !== false
            ? substr($markdown, $start, $end - $start)
            : $markdown;

        $body = (string) preg_replace('/\n+---\s*$/', '', $body);

        return (new GithubFlavoredMarkdownConverter())->convert(trim($body))->getContent();
    }
}
