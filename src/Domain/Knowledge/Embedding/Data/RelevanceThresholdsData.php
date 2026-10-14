<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding\Data;

use Spatie\LaravelData\Data;

/**
 * The scores a chunk must reach to be given to the model (`knowledge`) and a
 * tool to be offered (`toolRead`, `toolWrite`; a wrong write tool costs more,
 * so its bar can be higher): a reranking model's relevance scores, or an
 * embedding model's cosine similarities. Each model scores on its own scale,
 * so each has its own.
 */
final class RelevanceThresholdsData extends Data
{
    public function __construct(
        public float $knowledge,
        public float $toolRead,
        public float $toolWrite,
    ) {}
}
