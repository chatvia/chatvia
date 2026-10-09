<?php

namespace App\Http\Controllers\Widget\Forms;

use App\Http\Controllers\Widget\Concerns\InteractsWithWidgetRequest;
use App\Http\Requests\Chat\Forms\SubmitInlineFormRequest;
use App\Http\Widget\WidgetAgent;
use Domain\Agent\Models\Agent;
use Domain\Form\Actions\SubmitFormAction;
use Domain\Form\Data\SubmitFormData;
use Domain\Form\Enums\FormClaimFailure;
use Domain\Form\Exceptions\FormNotClaimableException;
use Domain\Form\Models\FormSubmission;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Http\JsonResponse;

/**
 * Inline-form submit from the embedded widget. The form's conversation must
 * belong to this agent (and, on a protected agent, to the signed-in chatter),
 * checked through {@see WidgetVerification} like every other widget call, so a
 * visitor cannot submit into someone else's thread.
 */
class SubmitInlineFormController
{
    use InteractsWithWidgetRequest;

    public function __invoke(
        SubmitInlineFormRequest $request,
        #[WidgetAgent] Agent $agent,
        FormSubmission $formInstance,
        SubmitFormAction $action,
        WidgetVerification $verification,
    ): JsonResponse {
        abort_unless($formInstance->agent_id === $agent->id, JsonResponse::HTTP_NOT_FOUND);

        $conversation = $verification->verifyConversationId(
            $formInstance->conversation?->external_id,
            $agent,
            $this->widgetUser($request),
        );
        abort_unless($conversation !== null, JsonResponse::HTTP_FORBIDDEN);

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
