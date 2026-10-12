<?php

declare(strict_types=1);

namespace Domain\Llm\Services;

use Domain\Llm\Enums\LlmRole;
use Domain\Llm\Exceptions\NoLlmAvailableException;
use Domain\Llm\Models\Llm;

/**
 * The catalog model behind each {@see LlmRole}: the one picked in Settings →
 * AI while it may be used, else the chat model, else the first model of the
 * catalog that may be used.
 */
class ModelRoles
{
    /**
     * @throws NoLlmAvailableException
     */
    public function model(LlmRole $role): Llm
    {
        return $this->find($role) ?? throw new NoLlmAvailableException;
    }

    /** The role's model, or null when the catalog has none that may be used. */
    public function find(LlmRole $role): ?Llm
    {
        return $this->picked($role)
            ?? ($role === LlmRole::Chat ? null : $this->picked(LlmRole::Chat))
            ?? Llm::available()->first();
    }

    private function picked(LlmRole $role): ?Llm
    {
        $alias = (string) config($role->configKey());

        if ($alias === '') {
            return null;
        }

        $model = Llm::catalog()->firstWhere('external_name', $alias);

        return $model?->isAvailable() ? $model : null;
    }
}
