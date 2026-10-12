<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Services;

use Domain\Knowledge\Ingestion\Services\Chunking\ChunkFit\ChunkFitClient;
use Domain\Knowledge\Ingestion\Services\Chunking\ChunkFit\Exceptions\ChunkFitException;
use Domain\Llm\Services\TikToken;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Counts tokens with the tokenizer chosen in the admin panel
 * (`chatvia.ai.tokenizer`, Settings → Chunking), for the stored size of each
 * chunk. The chunker counts them (POST /v1/tokens) when it is set up; when it
 * is not, or cannot answer, the built-in tokenizer ({@see TikToken}) counts
 * them with the same tokenizer. A chunker without the endpoint is not asked
 * again for a while.
 */
final readonly class TokenCounter
{
    private const string UNSUPPORTED_KEY = 'chunker:tokens-unsupported';

    private const int UNSUPPORTED_SECONDS = 600;

    public function __construct(
        private ChunkFitClient $chunker,
        private Cache $cache,
    ) {}

    public function count(string $text): int
    {
        return $this->countMany([$text])[0];
    }

    /**
     * @param  list<string>  $texts
     * @return list<int> in the order of $texts
     */
    public function countMany(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        $texts = array_values($texts);

        if ($this->chunker->isAvailable() && ! $this->cache->has($this->unsupportedKey())) {
            try {
                return $this->chunker->countTokens($texts, TikToken::encoding());
            } catch (ChunkFitException $e) {
                // An older chunker has no /v1/tokens: stop asking it for a
                // while. Any other failure is retried on the next count.
                if (in_array($e->getCode(), [404, 405], true)) {
                    $this->cache->put($this->unsupportedKey(), true, self::UNSUPPORTED_SECONDS);
                }
            }
        }

        return array_map(TikToken::countTokens(...), $texts);
    }

    private function unsupportedKey(): string
    {
        return self::UNSUPPORTED_KEY.':'.md5((string) config('services.chunkfit.base_url'));
    }
}
