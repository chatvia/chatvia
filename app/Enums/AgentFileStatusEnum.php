<?php

namespace App\Enums;

enum AgentFileStatusEnum: string
{
    case UPLOADED = 'uploaded';
    case PENDING = 'pending';
    case PROCESSING = 'ingesting';
    case FAILED = 'failed';
    case COMPLETED = 'ingested';
}
