<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * How far one person got in the dashboard tour (`users.onboarding_tour`).
 * Null: not started, so the dashboard opens it. `Agent`: the dashboard part
 * is done and the agent part waits on the next agent page. `Done`: finished
 * or skipped; "Take the tour" in the user menu sets it back to null.
 */
#[TypeScript]
enum OnboardingTourStage: string
{
    case Agent = 'agent';
    case Done = 'done';
}
