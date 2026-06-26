<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $with = ['contains', 'regex', 'exact', 'json_schema', 'llm_judge', 'tool_call'];

    private array $without = ['contains', 'regex', 'exact', 'json_schema', 'llm_judge'];

    public function up(): void
    {
        $this->setScorerTypes($this->with);
    }

    public function down(): void
    {
        $this->setScorerTypes($this->without);
    }

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
