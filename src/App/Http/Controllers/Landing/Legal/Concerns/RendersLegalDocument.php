<?php

namespace App\Http\Controllers\Landing\Legal\Concerns;

use Illuminate\Support\Carbon;
use League\CommonMark\GithubFlavoredMarkdownConverter;

trait RendersLegalDocument
{
    /**
     * Read a compliance markdown document and render it for a public page.
     *
     * Strips the internal-only framing so it never leaks to visitors: the H1
     * (which becomes the page title), the "Classification: Internal" metadata
     * table, and the trailing "Template … review with counsel" disclaimer.
     *
     * @return array{title: string, html: string, effectiveDate: ?string}
     */
    protected function legalDocument(string $relativePath): array
    {
        $markdown = (string) file_get_contents(base_path($relativePath));

        preg_match('/^#\s+(.+)$/m', $markdown, $titleMatch);
        $title = trim($titleMatch[1] ?? 'Legal');

        $effectiveDate = null;
        if (preg_match('/\|\s*\*\*Effective date\*\*\s*\|\s*([0-9-]+)\s*\|/', $markdown, $dateMatch)) {
            $effectiveDate = Carbon::parse($dateMatch[1])->isoFormat('D MMMM YYYY');
        }

        // Remove the first H1 — it is promoted to the page title.
        $markdown = (string) preg_replace('/^#\s+.+\n/m', '', $markdown, 1);

        // Remove the internal metadata table (Owner/Version/…/Classification).
        $markdown = (string) preg_replace(
            '/\|\s*\|\s*\|\n\|-+\|-+\|\n(?:\|.*\n)*?\|\s*\*\*Classification\*\*\s*\|[^\n]*\n/',
            '',
            $markdown,
            1
        );

        // Remove the trailing "Template prepared … counsel" disclaimer.
        $markdown = (string) preg_replace('/\n>\s*Template prepared.*$/s', '', $markdown);

        $html = (new GithubFlavoredMarkdownConverter)
            ->convert(trim($markdown))
            ->getContent();

        return [
            'title' => $title,
            'html' => $html,
            'effectiveDate' => $effectiveDate,
        ];
    }
}
