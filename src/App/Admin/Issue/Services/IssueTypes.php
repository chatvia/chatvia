<?php

declare(strict_types=1);

namespace App\Admin\Issue\Services;

use App\Admin\Issue\Types\IssueType;
use Domain\Llm\Enums\LlmFailure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

/**
 * The kinds of issue Radar knows, as listed in config/radar.php: one
 * {@see IssueType} class each, found by key or by the refused call it
 * stands for.
 */
final class IssueTypes
{
    /** @return Collection<string, IssueType> By key. */
    public function all(): Collection
    {
        return once(fn (): Collection => collect(Config::array('radar.types', []))
            ->map(fn (mixed $class): mixed => is_scalar($class) ? app((string) $class) : null)
            ->filter(fn (mixed $type): bool => $type instanceof IssueType)
            ->keyBy(fn (IssueType $type): string => $type->key()));
    }

    public function for(string $key): ?IssueType
    {
        return $this->all()->get($key);
    }

    public function forFailure(LlmFailure $failure): ?IssueType
    {
        return $this->all()->first(fn (IssueType $type): bool => $type->failure() === $failure);
    }
}
