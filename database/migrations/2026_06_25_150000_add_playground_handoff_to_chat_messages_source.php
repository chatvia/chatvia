<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $with = [
        'hosted_chat', 'widget', 'bar', 'telegram', 'slack',
        'messenger', 'whatsapp', 'msteams', 'instagram', 'playground', 'handoff',
    ];

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

    private function setSources(array $values): void
    {
        $list = implode(', ', array_map(static fn (string $v): string => "'".$v."'", $values));
        $constraint = 'chat_messages_source_check';

        DB::statement("ALTER TABLE chat_messages DROP CONSTRAINT IF EXISTS {$constraint}");
        DB::statement("ALTER TABLE chat_messages ADD CONSTRAINT {$constraint} CHECK ((source)::text = ANY ((ARRAY[{$list}])::text[]))");
    }
};
