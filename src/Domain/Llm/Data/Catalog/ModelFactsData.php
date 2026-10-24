<?php

declare(strict_types=1);

namespace Domain\Llm\Data\Catalog;

use Spatie\LaravelData\Data;

/**
 * What the models.dev catalog says about one model: whether it reasons, the
 * reasoning effort values it takes (null when it lists none, or reasons
 * without a named effort), whether it takes a temperature, calls tools and
 * keeps a reply to a JSON schema, the longest reply it writes in tokens,
 * whether it reads images (an image among its inputs), and whether it reads
 * PDF files (a PDF among its inputs).
 * Anything the catalog does not say is null.
 */
final class ModelFactsData extends Data
{
    /**
     * @param  list<string>|null  $efforts
     */
    public function __construct(
        public bool $reasoning,
        public ?array $efforts,
        public ?bool $takesTemperature,
        public ?int $maxOutputTokens = null,
        public ?bool $callsTools = null,
        public ?bool $takesResponseSchema = null,
        public ?bool $readsImages = null,
        public ?bool $readsFiles = null,
    ) {}

    /**
     * The facts in one catalog entry.
     *
     * @param  array<array-key, mixed>  $entry
     */
    public static function fromCatalog(array $entry): self
    {
        $efforts = null;

        foreach ((array) ($entry['reasoning_options'] ?? []) as $option) {
            if (is_array($option) && ($option['type'] ?? null) === 'effort' && is_array($option['values'] ?? null)) {
                $efforts = array_values(array_filter($option['values'], filled(...)));
            }
        }

        $output = $entry['limit']['output'] ?? null;
        $inputs = $entry['modalities']['input'] ?? null;

        return new self(
            reasoning: (bool) ($entry['reasoning'] ?? false),
            efforts: $efforts !== [] ? $efforts : null,
            takesTemperature: is_bool($entry['temperature'] ?? null) ? $entry['temperature'] : null,
            maxOutputTokens: is_int($output) && $output > 0 ? $output : null,
            callsTools: is_bool($entry['tool_call'] ?? null) ? $entry['tool_call'] : null,
            takesResponseSchema: is_bool($entry['structured_output'] ?? null) ? $entry['structured_output'] : null,
            readsImages: is_array($inputs) ? in_array('image', $inputs, true) : null,
            readsFiles: is_array($inputs) ? in_array('pdf', $inputs, true) : null,
        );
    }
}
