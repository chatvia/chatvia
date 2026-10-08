<?php

use App\Identity\Workspace\Models\User;

/**
 * The account-scoped relations (agents/credits/quota/addons/sourceFiles) resolve
 * through workspace() = workspace_id ? workspace : self, so a workspace owner
 * and an employee under that workspace see the SAME account-owned data.
 *
 * This pins that lazy behaviour. #9 part 2 will make the relations eager-safe
 * too (today they are lazy-only — see the skipped regression target below).
 */
it('scopes account relations to the workspace owner for both owner and employee (lazy)', function (): void {
    $owner = User::factory()->create();
    $agent = createAgent($owner);

    $employee = User::factory()->employee($owner)->create();

    expect($owner->workspace()->id)->toBe($owner->id)
        ->and($employee->workspace()->id)->toBe($owner->id)
        // Owner sees their own agent; the employee resolves to the same account.
        ->and($owner->agents->pluck('id')->all())->toContain($agent->id)
        ->and($employee->agents->pluck('id')->all())->toContain($agent->id);
});

it('eager-loads account relations correctly for an employee', function (): void {
    // Regression target for #9 part 2. Today the workspace() relations are
    // lazy-only: eager-loading agents/quota/credits/addons on an employee builds
    // the relation on a bare instance and returns the wrong rows, worked around
    // by loadMissing(['parentWorkspace.X', 'X']) in HandleInertiaRequests.
    // The fix is a stored `effective_user_id`
    // (= COALESCE(workspace_id, id)) column used as the relations' local key —
    // which a migration must add, so this can only go green after migrate:fresh.
})->skip('#9 part 2: needs the effective_user_id column migration to make these relations eager-safe');
