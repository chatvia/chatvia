<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agent\Guardrail;

use App\Http\Requests\Agent\Guardrail\BlockGuardrailOffenderRequest;
use App\Identity\Chatter\Actions\BlockChatterAction;
use Domain\Agent\Models\Agent;
use Domain\Guardrail\Models\GuardrailEvent;
use Domain\Security\Actions\BlockVisitorAction;
use Illuminate\Http\RedirectResponse;

/**
 * Blocks the person behind an abusive guardrail trigger: switches a chatter's
 * account off (and may email them), or blocks an anonymous visitor by
 * fingerprint. The request checks who may do which.
 */
class BlockGuardrailOffenderController
{
    public function __invoke(
        BlockGuardrailOffenderRequest $request,
        Agent $agent,
        GuardrailEvent $event,
        BlockChatterAction $blockChatter,
        BlockVisitorAction $blockVisitor,
    ): RedirectResponse {
        if ($request->blocksChatter()) {
            $blockChatter->execute($request->toChatterData());
        } else {
            $blockVisitor->execute($request->toVisitorData());
        }

        return back();
    }
}
