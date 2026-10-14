<?php

declare(strict_types=1);

namespace App\Admin\Settings\Data;

use Domain\Knowledge\Embedding\Rerank\RerankerFactory;
use Spatie\LaravelData\Data;

/**
 * The reranker the admin setup picked: OpenRouter or Cohere with one of
 * their models, a server of your own (its address, model and, when it asks
 * for one, key), or none. The driver is one of {@see RerankerFactory::DRIVERS}
 * other than "auto": the setup saves a plain choice.
 *
 * The key is OpenRouter's or Cohere's (saved in Settings → AI providers), or
 * the server's. A null key keeps the saved one, as a key typed in an earlier
 * step is saved there.
 */
final class RerankerSetupData extends Data
{
    /**
     * @param  'openrouter'|'cohere'|'custom'|'none'  $driver
     */
    public function __construct(
        public string $driver,
        public ?string $model = null,
        public ?string $apiKey = null,
        public ?string $url = null,
    ) {}

    /** Whether a reranker is on: anything but "none". */
    public function isOn(): bool
    {
        return $this->driver !== 'none';
    }
}
