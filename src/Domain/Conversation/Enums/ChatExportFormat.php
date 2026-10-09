<?php

declare(strict_types=1);

namespace Domain\Conversation\Enums;

/**
 * The file formats a visitor can download a conversation (or one message) in.
 */
enum ChatExportFormat: string
{
    case Pdf = 'pdf';
    case Text = 'text';
    case Markdown = 'markdown';
}
