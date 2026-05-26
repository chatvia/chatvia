<?php

namespace Domain\Sources\QA\Actions;

use Domain\Sources\QA\Models\AgentQuestionAnswer;

class DeleteAgentQaAction
{
    public function execute(AgentQuestionAnswer $qa): void
    {
        $qa->delete();
    }
}
