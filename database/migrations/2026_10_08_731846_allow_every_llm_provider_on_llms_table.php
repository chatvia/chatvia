<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `llms.provider` uses the same provider slugs as `chatvia.ai.provider`
 * (LlmProviderEnum), so the CHECK constraint must also allow the two providers
 * the app can route to but the catalog column did not list yet.
 */
return new class extends Migration
{
    private array $before = ['scaleway', 'openai', 'anthropic', 'mistral', 'google'];

    private array $after = ['scaleway', 'openai', 'anthropic', 'mistral', 'google', 'openrouter', 'groq'];

    public function up(): void
    {
        $this->setProviders($this->after);
    }

    public function down(): void
    {
        $this->setProviders($this->before);
    }

    private function setProviders(array $values): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $list = implode(', ', array_map(static fn (string $v): string => "'".$v."'", $values));
        $constraint = 'llms_provider_check';

        DB::statement("ALTER TABLE llms DROP CONSTRAINT IF EXISTS {$constraint}");
        DB::statement("ALTER TABLE llms ADD CONSTRAINT {$constraint} CHECK ((provider)::text = ANY ((ARRAY[{$list}])::text[]))");
    }
};
