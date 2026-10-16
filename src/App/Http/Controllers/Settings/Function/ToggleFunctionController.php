<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings\Function;

use Domain\Integration\Function\Models\AgentFunction;
use Illuminate\Http\RedirectResponse;

/**
 * Flip a workspace function's on/off switch. The route binds `{function}`;
 * `workspace.scope` 404s another workspace's.
 */
class ToggleFunctionController
{
    public function __invoke(AgentFunction $function): RedirectResponse
    {
        $function->toggleEnabled();

        return back();
    }
}
