<?php

namespace Domain\Knowledge\Source\Enums;

/**
 * The shared ingestion lifecycle status for every knowledge source type
 * (File, Web, Text, Youtube, ...). Single source of truth for the status
 * vocabulary that was previously hand-written as magic strings across the
 * ingestion Actions, source models, query builders and stats resources.
 *
 * The string values are the persisted `status` column values; models keep the
 * column as a plain string, so reads/writes use `SourceStatus::X->value`.
 */
enum SourceStatus: string
{
    case Uploaded = 'uploaded';
    case Pending = 'pending';
    case Processing = 'ingesting';
    case Failed = 'failed';
    case Completed = 'ingested';
}
