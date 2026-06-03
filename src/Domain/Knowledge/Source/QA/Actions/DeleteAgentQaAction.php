<?php

namespace Domain\Knowledge\Source\QA\Actions;

use Domain\Knowledge\Source\QA\Models\AgentQuestionAnswer;

class DeleteAgentQaAction
{
    public function execute(AgentQuestionAnswer $qa): void
    {
        $qa->delete();
    }
}
