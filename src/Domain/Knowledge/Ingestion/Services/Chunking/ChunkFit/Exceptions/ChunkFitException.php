<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Services\Chunking\ChunkFit\Exceptions;

use RuntimeException;

/**
 * The chunk.fit service could not split a text (unreachable, or an error
 * reply).
 */
class ChunkFitException extends RuntimeException {}
