<?php

namespace Domain\Integration\Function\Actions\Concerns;

use Closure;
use Domain\Integration\Function\Enums\FunctionWebhookMethod;
use Domain\Integration\Function\Models\AgentFunction;
use Illuminate\Database\UniqueConstraintViolationException;
use Support\Exceptions\DomainValidationException;

/**
 * Rules shared by creating and updating a webhook function: names are unique
 * per workspace, and the approval gate follows the HTTP verb unless the
 * caller chose otherwise.
 */
trait GuardsFunctionDefinition
{
    /**
     * Names are unique per workspace (a DB unique index). Check up front so a
     * clash is a validation error rather than a database error.
     *
     * @throws DomainValidationException
     */
    private function ensureNameAvailable(int $ownerId, string $internalName, ?int $exceptFunctionId = null): void
    {
        $taken = AgentFunction::query()
            ->where('workspace_id', $ownerId)
            ->where('internal_name', $internalName)
            ->when($exceptFunctionId !== null, fn ($query) => $query->whereKeyNot($exceptFunctionId))
            ->exists();

        if ($taken) {
            throw $this->duplicateNameException();
        }
    }

    /**
     * Run a write, turning a unique-index race (two saves with the same name
     * at once) into the same validation error as the up-front check.
     *
     * @template T
     *
     * @param  Closure(): T  $write
     * @return T
     */
    private function guardUniqueName(Closure $write): mixed
    {
        try {
            return $write();
        } catch (UniqueConstraintViolationException) {
            throw $this->duplicateNameException();
        }
    }

    /**
     * When an update switches the verb and the caller did not set the
     * approval flag, only ever tighten it: a GET function that becomes a
     * POST must not keep running without approval.
     */
    private function approvalAfterMethodChange(AgentFunction $function, FunctionWebhookMethod $newMethod): ?bool
    {
        return $newMethod !== $function->webhook_method && ! $function->requires_approval && $newMethod->changesData()
            ? true
            : null;
    }

    /**
     * Rendered as a 422 on `internal_name` by the exception handler, the same
     * shape a FormRequest rule would give.
     */
    private function duplicateNameException(): DomainValidationException
    {
        return new DomainValidationException('internal_name', __('A function with this name already exists in your workspace.'));
    }
}
