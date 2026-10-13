<?php

declare(strict_types=1);

namespace Domain\Knowledge\Retrieval\Enums;

/**
 * What kind of source a piece of retrieved knowledge comes from, for the
 * sources list under an answer. The value is sent to the chat clients, so
 * never rename a case value.
 */
enum SourceKind: string
{
    case File = 'file';
    case Media = 'media';
    case Website = 'website';
    case Text = 'text';
    case Video = 'video';
}
