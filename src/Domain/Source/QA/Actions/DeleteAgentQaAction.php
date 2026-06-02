<?php

namespace Domain\Source\QA\Actions;

use Domain\Source\QA\Models\AgentQuestionAnswer;

class DeleteAgentQaAction
{
    public function execute(AgentQuestionAnswer $qa): void
    {
        $qa->delete();
    }
}
