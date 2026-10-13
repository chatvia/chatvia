<?php

declare(strict_types=1);

namespace Domain\Knowledge\Retrieval\Enums;

/**
 * How a knowledge search reaches an agent's chunks, picked from how many
 * chunks the agent can reach (counted up to the exact-search limit).
 */
enum ChunkSearchScope: string
{
    /** The agent reaches no chunks: nothing to search. */
    case None = 'none';

    /** Few chunks: score every one of them exactly, no index involved. */
    case Exact = 'exact';

    /** Many chunks: let the vector and BM25 indexes find the closest ones. */
    case Indexed = 'indexed';
}
