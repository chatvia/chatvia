<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lets the governance audit record a call to a tool the agent was not offered
 * on that turn (`ToolCallOutcome::NotOffered`). `agent_activity.outcome` has a
 * Postgres CHECK, so the full list of values is set again.
 */
return new class extends Migration
{
    private const string CONSTRAINT = 'agent_activity_outcome_check';

    /** @var list<string> */
    private array $before = ['auto', 'approved', 'denied', 'rate_limited'];

    /** @var list<string> */
    private array $after = ['auto', 'approved', 'denied', 'rate_limited', 'not_offered'];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE agent_activity DROP CONSTRAINT IF EXISTS '.self::CONSTRAINT);
        DB::statement('ALTER TABLE agent_activity ADD CONSTRAINT '.self::CONSTRAINT.' '.$this->check($this->after));
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // NOT VALID: audit rows are immutable, so rows already written with the
        // new value stay; only new writes are checked against the old list.
        DB::statement('ALTER TABLE agent_activity DROP CONSTRAINT IF EXISTS '.self::CONSTRAINT);
        DB::statement('ALTER TABLE agent_activity ADD CONSTRAINT '.self::CONSTRAINT.' '.$this->check($this->before).' NOT VALID');
    }

    /**
     * @param  list<string>  $values
     */
    private function check(array $values): string
    {
        $list = implode(', ', array_map(static fn (string $value): string => "'".$value."'", $values));

        return "CHECK ((outcome)::text = ANY ((ARRAY[{$list}])::text[]))";
    }
};
