<?php

namespace App\Services;

use Symfony\Component\DomCrawler\Crawler as DOMCrawler;

class HTMLCleaner
{
    private function unwantedTags(): array
    {
        // TODO: this should be the way around (allowed list not blacklist)
        return [
            'script',
            'style',
            'noscript',
            'svg',
            'img',
            'canvas',
            'iframe',
            'object',
            'embed',
            'video',
            'audio',
            'form',
            'input',
            'button',
            'select',
            'textarea',
            'nav',
        ];
    }

    private function domCrawler($html): DOMCrawler
    {
        $crawler = new DOMCrawler($html);

        foreach ($this->unwantedTags() as $selector) {
            $crawler->filter($selector)->each(function (DOMCrawler $node) {
                if ($node->getNode(0) && $node->getNode(0)->parentNode) {
                    $node->getNode(0)->parentNode->removeChild($node->getNode(0));
                }
            });
        }

        return $crawler->filter('body');
    }

    public function getTextFromHtml(string $html): string
    {
        return $this->cleanText($this->domCrawler($html)->text());
    }

    private function cleanText(string $text): string
    {
        // Remove null bytes and control characters
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

        // Ensure proper UTF-8 encoding
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', mb_detect_encoding($text));
        }

        // Remove any remaining replacement characters
        $text = preg_replace('/[\x{FFFD}]/u', '', $text);

        return trim($text);
    }
}
