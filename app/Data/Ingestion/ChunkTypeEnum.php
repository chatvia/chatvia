<?php

namespace App\Data\Ingestion;

enum ChunkTypeEnum: string
{
    case TEXT = 'text';
    case IMAGE = 'image';
    case TABLES = 'table';
}
