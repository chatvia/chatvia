<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Widen chat_messages.source to cover the non-channel message markers the
 * streaming + handoff paths emit but the original enum() never listed:
 *   - 'playground' — widget Live Preview turns (StreamRequestDataFactory::forWidget,
 *     gated on $isPlayground), and
 *   - 'handoff'    — visitor messages relayed while a conversation is handed off
 *     to a human (SendVisitorHandoffMessageAction).
 *
 * Laravel's enum() compiles to a varchar + CHECK on Postgres (no native enum
 * type), so a new value can't just be appended — the chat_messages_source_check
 * constraint must be dropped and recreated. Same shape as the
 * chat_conversations.source / eval *_scorer_type_check widenings.
 *
 * Why it matters: StoreMessageJob runs on the queue, so a CHECK violation here
 * failed silently off the request path — every playground/handoff turn lost its
 * persisted message (and, for the assistant turn, its inline-form artifact), so
 * reloads dropped the turn and inline-form submits couldn't resolve their owning
 * message. The column stays nullable; NULL still satisfies the CHECK.
 *
 * Forward-only (the app is in production): no shipped migration is edited.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $with = [
        'hosted_chat', 'widget', 'bar', 'telegram', 'slack',
        'messenger', 'whatsapp', 'msteams', 'instagram', 'playground', 'handoff',
    ];

    /** @var list<string> */
    private array $without = [
        'hosted_chat', 'widget', 'bar', 'telegram', 'slack',
        'messenger', 'whatsapp', 'msteams', 'instagram',
    ];

    public function up(): void
    {
        $this->setSources($this->with);
    }

    public function down(): void
    {
        $this->setSources($this->without);
    }

    /**
     * @param  list<string>  $values
     */
    private function setSources(array $values): void
    {
        $list = implode(', ', array_map(static fn (string $v): string => "'".$v."'", $values));
        $constraint = 'chat_messages_source_check';

        DB::statement("ALTER TABLE chat_messages DROP CONSTRAINT IF EXISTS {$constraint}");
        DB::statement("ALTER TABLE chat_messages ADD CONSTRAINT {$constraint} CHECK ((source)::text = ANY ((ARRAY[{$list}])::text[]))");
    }
};
