<?php

namespace App\Enums;

enum AgentFileStatusEnum: string
{
    case UPLOADED = 'uploaded';
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case FAILED = 'failed';
    case COMPLETED = 'completed';
}
