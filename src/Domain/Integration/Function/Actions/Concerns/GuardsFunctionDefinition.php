<?php

namespace Domain\Integration\Function\Actions\Concerns;

use Closure;
use Domain\Integration\Function\Enums\FunctionWebhookMethod;
use Domain\Integration\Function\Models\AgentFunction;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * Rules shared by every create/update path for a webhook function (dashboard
 * and API), so the paths can't drift apart: function names are unique per
 * workspace, and the approval gate follows the HTTP verb unless the caller
 * chose otherwise.
 */
trait GuardsFunctionDefinition
{
    /**
     * Names are unique per workspace (a DB unique index). Check up front so a
     * clash is a validation error rather than a database error.
     */
    private function ensureNameAvailable(int $ownerId, string $internalName, ?int $exceptFunctionId = null): void
    {
        $taken = AgentFunction::query()
            ->where('user_id', $ownerId)
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
     * Reads (GET) run without approval by default; any verb that can change
     * data asks first.
     */
    private function approvalDefaultFor(string $method): bool
    {
        return FunctionWebhookMethod::tryFrom(strtoupper($method)) !== FunctionWebhookMethod::Get;
    }

    /**
     * When an update switches the verb and the caller did not set the
     * approval flag, only ever tighten it: a GET function that becomes a
     * POST must not keep running without approval.
     */
    private function approvalAfterMethodChange(AgentFunction $function, string $newMethod): ?bool
    {
        $changed = strtoupper($newMethod) !== strtoupper((string) $function->webhook_method);

        return $changed && ! $function->requires_approval && $this->approvalDefaultFor($newMethod)
            ? true
            : null;
    }

    private function duplicateNameException(): ValidationException
    {
        return ValidationException::withMessages([
            'internal_name' => 'A function with this name already exists in your workspace.',
        ]);
    }
}
