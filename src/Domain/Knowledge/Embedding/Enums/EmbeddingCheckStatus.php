<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding\Enums;

/**
 * How a test embedding went: it worked, the key was refused, the model did
 * not work, the service could not be reached, it returned no vector, or a
 * vector of another size than the database stores.
 */
enum EmbeddingCheckStatus: string
{
    case Ok = 'ok';
    case KeyRefused = 'key_refused';
    case ModelFailed = 'model_failed';
    case Unreachable = 'unreachable';
    case Empty = 'empty';
    case WrongSize = 'wrong_size';
}
