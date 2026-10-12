<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Services\Chunking\ChunkFit\Exceptions;

use RuntimeException;

/**
 * The chunker (chunk.fit or self-hosted) could not split or count a text:
 * unreachable, or an error reply. The code is the HTTP status of a reply.
 */
class ChunkFitException extends RuntimeException {}
