<?php

namespace App\Http\Rules;

use App\Identity\Workspace\Models\Workspace;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * `exists` and `unique` validation rules that look only inside one workspace.
 *
 * A bare `exists:source_files,id` checks the whole table, so a guessed id of
 * another workspace passes validation; a bare `unique:functions,name` makes a
 * name taken by another workspace look taken. Form requests use these instead
 * (`ConventionsTest` refuses the bare forms outside a short allowlist of
 * install-wide values, such as an account's email).
 */
final class WorkspaceRule
{
    public static function exists(string $table, Workspace|int $workspace, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->where('workspace_id', self::id($workspace));
    }

    /**
     * Like {@see self::exists()}, but also accepts the built-in rows every workspace
     * shares (`workspace_id` null), such as the system topics.
     */
    public static function existsOrBuiltIn(string $table, Workspace|int $workspace, string $column = 'id'): Exists
    {
        $workspaceId = self::id($workspace);

        return Rule::exists($table, $column)->where(fn (Builder $query): Builder => $query
            ->whereNull('workspace_id')
            ->orWhere('workspace_id', $workspaceId));
    }

    public static function unique(string $table, Workspace|int $workspace, string $column = 'NULL'): Unique
    {
        return Rule::unique($table, $column)->where('workspace_id', self::id($workspace));
    }

    private static function id(Workspace|int $workspace): int
    {
        return $workspace instanceof Workspace ? $workspace->id : $workspace;
    }
}
