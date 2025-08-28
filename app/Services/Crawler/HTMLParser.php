<?php

namespace App\Services\Crawler;

use Symfony\Component\DomCrawler\Crawler as DOMCrawler;

class HTMLParser
{
    public function getTextFromHtml(string $html): string
    {
        $crawler = new DOMCrawler($html);

        $unwantedSelectors = [
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

        foreach ($unwantedSelectors as $selector) {
            $crawler->filter($selector)->each(function (DOMCrawler $node) {
                if ($node->getNode(0) && $node->getNode(0)->parentNode) {
                    $node->getNode(0)->parentNode->removeChild($node->getNode(0));
                }
            });
        }

        $text = $crawler
            ->filter('body')
            ->text();

        return $this->cleanText($text);
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
