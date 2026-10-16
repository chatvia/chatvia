<?php

declare(strict_types=1);

namespace Domain\Form\Actions;

use Domain\Agent\Models\Agent;
use Domain\Form\Listeners\PushSubmissionToCrm;

/**
 * Counts one failed CRM webhook delivery (all retries used up) for an agent.
 * After `chatvia.crm.max_failures` consecutive failures the webhook switches
 * itself off, so a dead endpoint stops burning queue retries. A successful
 * delivery resets the counter ({@see PushSubmissionToCrm}).
 */
class RecordCrmDeliveryFailureAction
{
    public function execute(Agent $agent): void
    {
        $crm = (array) ($agent->settings('leads')->get('crm') ?? []);

        $failures = (int) ($crm['webhook_failures'] ?? 0) + 1;
        $crm['webhook_failures'] = $failures;

        if ($failures >= (int) config('chatvia.crm.max_failures', 15)) {
            $crm['webhook_enabled'] = false;
        }

        $agent->setSetting('crm', $crm, 'leads');
    }
}
