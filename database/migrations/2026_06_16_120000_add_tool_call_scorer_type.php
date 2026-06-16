<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the `tool_call` scorer to the eval suite. Laravel's enum() column compiles
 * to a varchar + CHECK constraint on Postgres (no native enum type), so a new
 * value can't just be appended — the {table}_scorer_type_check constraints on
 * eval_cases and eval_results must be dropped and recreated with the value added.
 * Same shape as the chat_conversations.source CHECK widening.
 *
 * Forward-only (the app is in production): no shipped migration is edited.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $with = ['contains', 'regex', 'exact', 'json_schema', 'llm_judge', 'tool_call'];

    /** @var list<string> */
    private array $without = ['contains', 'regex', 'exact', 'json_schema', 'llm_judge'];

    public function up(): void
    {
        $this->setScorerTypes($this->with);
    }

    public function down(): void
    {
        $this->setScorerTypes($this->without);
    }

    /**
     * @param  list<string>  $values
     */
    private function setScorerTypes(array $values): void
    {
        $list = implode(', ', array_map(fn (string $v): string => "'".$v."'", $values));

        foreach (['eval_cases', 'eval_results'] as $table) {
            $constraint = $table.'_scorer_type_check';

            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$constraint}");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$constraint} CHECK (scorer_type::text = ANY (ARRAY[{$list}]::text[]))");
        }
    }
};
