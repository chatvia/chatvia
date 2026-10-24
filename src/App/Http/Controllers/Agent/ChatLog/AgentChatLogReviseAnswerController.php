<?php

namespace App\Http\Controllers\Agent\ChatLog;

use App\Http\Requests\Agent\ChatLog\ReviseAnswerRequest;
use Domain\Agent\Models\Agent;
use Domain\Evaluation\Actions\CreateEvalCasesFromQuestionsAction;
use Domain\Evaluation\Data\CreateEvalCasesFromQuestionsData;
use Domain\Evaluation\Data\KnowledgeQuestionItemData;
use Domain\Evaluation\Enums\EvalQuestionSource;
use Domain\Knowledge\Source\Qa\Actions\CreateQaFromMessageReviseAction;
use Illuminate\Http\JsonResponse;

/**
 * Turns an operator's corrected answer from the chat log into a Q&A entry
 * and, when asked, into an eval case judged against it, so a later change
 * that breaks the fix shows up in the next eval run.
 */
class AgentChatLogReviseAnswerController
{
    public function __invoke(
        ReviseAnswerRequest $request,
        Agent $agent,
        CreateQaFromMessageReviseAction $createQa,
        CreateEvalCasesFromQuestionsAction $createEvalCases,
    ): JsonResponse {
        $qa = $createQa->execute(
            agent: $agent,
            messageId: $request->messageId(),
            userMessage: $request->userMessage(),
            expectedResponse: $request->expectedResponse(),
        );

        // A question already in the eval cases is skipped, so this may add none.
        $evalCases = $request->addEvalCase()
            ? $createEvalCases->execute(new CreateEvalCasesFromQuestionsData([
                new KnowledgeQuestionItemData(EvalQuestionSource::Qa, $qa->id, $request->userMessage()),
            ]), $agent)
            : null;

        return response()->json([
            'success' => true,
            'message' => __('Q&A entry created successfully. Future similar questions will use this answer.'),
            'qa_id' => $qa->id,
            'eval_case_created' => $evalCases !== null && $evalCases->isNotEmpty(),
        ]);
    }
}
