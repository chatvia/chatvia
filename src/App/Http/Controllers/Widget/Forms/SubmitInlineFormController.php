<?php

namespace App\Http\Controllers\Widget\Forms;

use App\Http\Controllers\Widget\Concerns\InteractsWithWidgetRequest;
use App\Http\Requests\Chat\Forms\SubmitInlineFormRequest;
use App\Http\Widget\WidgetAgent;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Conversations\FindWidgetConversationAction;
use Domain\Conversation\Models\Conversation;
use Domain\Form\Actions\SubmitFormAction;
use Domain\Form\Data\SubmitFormData;
use Domain\Form\Enums\FormClaimFailure;
use Domain\Form\Exceptions\FormNotClaimableException;
use Domain\Form\Models\FormSubmission;
use Illuminate\Http\JsonResponse;

/**
 * Inline-form submit from the embedded widget. The form's conversation must
 * be in the caller's widget session with this agent (and, on a protected
 * agent, the signed-in chatter's), as for the widget's other session-scoped
 * calls, so a visitor cannot submit into someone else's thread.
 */
class SubmitInlineFormController
{
    use InteractsWithWidgetRequest;

    public function __invoke(
        SubmitInlineFormRequest $request,
        #[WidgetAgent] Agent $agent,
        FormSubmission $formInstance,
        SubmitFormAction $action,
        FindWidgetConversationAction $findConversation,
    ): JsonResponse {
        abort_unless($formInstance->agent_id === $agent->id, JsonResponse::HTTP_NOT_FOUND);

        $conversation = $findConversation->execute(
            (string) $formInstance->conversation?->external_id,
            (string) $this->widgetSessionId($request),
            $agent,
            $this->widgetViewer($request),
        );
        abort_unless($conversation instanceof Conversation, JsonResponse::HTTP_FORBIDDEN);

        try {
            $result = $action->execute(new SubmitFormData(
                submission: $formInstance,
                conversation: $conversation,
                values: $request->fieldValues(),
                ip: $request->ip(),
                userAgent: $request->userAgent(),
                requestId: $request->requestId(),
                assistantMessageExternalId: $request->assistantMessageId(),
            ));
        } catch (FormNotClaimableException $e) {
            return response()->json([
                'status' => $e->reason->value,
                'message' => $e->getMessage(),
            ], $e->reason === FormClaimFailure::Expired ? JsonResponse::HTTP_GONE : JsonResponse::HTTP_CONFLICT);
        }

        return response()->json($result);
    }
}
