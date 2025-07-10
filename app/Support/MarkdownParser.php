<?php

namespace App\Support;

class MarkdownParser
{
    public function __construct()
    {
        // No setup needed for simple text-based parsing
    }

    /**
     * Parse a markdown file and extract headings with their content
     *
     * @param string $filePath Path to the markdown file
     * @return array Array of sections with headings and content
     * @throws \Exception If file doesn't exist or can't be read
     */
    public function parseFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception("File not found: {$filePath}");
        }

        $markdown = file_get_contents($filePath);
        
        if ($markdown === false) {
            throw new \Exception("Could not read file: {$filePath}");
        }

        return $this->parseContent($markdown);
    }

    /**
     * Parse markdown content and extract headings with their content
     *
     * @param string $markdown The markdown content to parse
     * @return array Array of sections with headings and content
     */
    public function parseContent(string $markdown): array
    {
        // Split markdown into lines for easier processing
        $lines = explode("\n", $markdown);
        $sections = [];
        $currentSection = null;
        $contentLines = [];

        foreach ($lines as $lineIndex => $line) {
            // Check if line is a heading (# ## ###)
            if (preg_match('/^(#{1,3})\s+(.+)$/', $line, $matches)) {
                $level = strlen($matches[1]);
                $title = trim($matches[2]);

                // Save previous section if exists
                if ($currentSection !== null) {
                    $currentSection['content'] = trim(implode("\n", $contentLines));
                    $sections[] = $currentSection;
                }

                // Start new section
                $currentSection = [
                    'level' => $level,
                    'title' => $title,
                    'content' => '',
                    'raw_heading' => $line
                ];
                $contentLines = [];
            } else {
                // Collect content lines for current section
                if ($currentSection !== null) {
                    $contentLines[] = $line;
                }
            }
        }

        // Add the last section
        if ($currentSection !== null) {
            $currentSection['content'] = trim(implode("\n", $contentLines));
            $sections[] = $currentSection;
        }

        return $sections;
    }



    /**
     * Get only the headings from a markdown file (without content)
     *
     * @param string $filePath
     * @return array
     */
    public function getHeadings(string $filePath): array
    {
        $sections = $this->parseFile($filePath);
        
        return array_map(function ($section) {
            return [
                'level' => $section['level'],
                'title' => $section['title'],
                'raw_heading' => $section['raw_heading']
            ];
        }, $sections);
    }

    /**
     * Get sections by heading level
     *
     * @param string $filePath
     * @param int $level Heading level (1, 2, or 3)
     * @return array
     */
    public function getSectionsByLevel(string $filePath, int $level): array
    {
        if (!in_array($level, [1, 2, 3])) {
            throw new \InvalidArgumentException('Level must be 1, 2, or 3');
        }

        $sections = $this->parseFile($filePath);
        
        return array_filter($sections, function ($section) use ($level) {
            return $section['level'] === $level;
        });
    }

    /**
     * Find a section by title
     *
     * @param string $filePath
     * @param string $title
     * @return array|null
     */
    public function findSectionByTitle(string $filePath, string $title): ?array
    {
        $sections = $this->parseFile($filePath);
        
        foreach ($sections as $section) {
            if (strcasecmp($section['title'], $title) === 0) {
                return $section;
            }
        }

        return null;
    }
} 