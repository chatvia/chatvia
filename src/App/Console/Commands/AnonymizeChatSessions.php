<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Domain\Conversation\Actions\Retention\AnonymizeChatSessionsAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Daily visitor anonymization: clears the visitor details of old chat
 * sessions in each workspace that set an "anonymize after" window
 * ({@see AnonymizeChatSessionsAction}).
 */
#[Description('Clear visitor details (name, email, phone, IP address, browser string, location, page links) from chat sessions older than the "anonymize after" window each workspace set in its security policy. Messages are kept.')]
#[Signature('app:anonymize-chat-sessions {--batch=5000 : How many sessions to update per batch}')]
class AnonymizeChatSessions extends Command
{
    public function handle(AnonymizeChatSessionsAction $anonymize): int
    {
        $anonymized = $anonymize->execute(max(1, (int) $this->option('batch')));

        $this->info("Anonymized {$anonymized} chat session(s).");

        return self::SUCCESS;
    }
}
