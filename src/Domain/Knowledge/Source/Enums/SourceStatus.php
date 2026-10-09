<?php

namespace Domain\Knowledge\Source\Enums;

/**
 * The ingestion lifecycle status shared by every knowledge source type (file,
 * text, website, website URL, YouTube video). Every source model casts its
 * `status` column to this enum.
 *
 * The string values are the persisted column values and must not change. Each
 * source table has a Postgres CHECK constraint on `status`; together they
 * allow exactly these values. A new case needs a forward migration that widens
 * the constraint of every table that may store it.
 */
enum SourceStatus: string
{
    /** A file was uploaded but its ingestion has not been requested yet. */
    case Uploaded = 'uploaded';

    /** Waiting to be (re)ingested. */
    case Pending = 'pending';

    /** Website discovery (FireCrawl `/map`) is running. Websites only. */
    case Crawling = 'crawling';

    /** Website discovery finished; the URLs wait to be trained. Websites only. */
    case Crawled = 'crawled';

    /** Chunks are being created and embedded. */
    case Processing = 'ingesting';

    case Failed = 'failed';

    /** Ingestion finished; the source is searchable. */
    case Completed = 'ingested';
}
