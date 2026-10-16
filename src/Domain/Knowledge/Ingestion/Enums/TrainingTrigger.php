<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Enums;

/**
 * Who started a training run: a person (adding, retraining) or the hourly
 * website refresh. A scheduled refresh that succeeds tells nobody; a person
 * who started one wants to hear when it is done.
 */
enum TrainingTrigger: string
{
    case Manual = 'manual';
    case Scheduled = 'scheduled';
}
