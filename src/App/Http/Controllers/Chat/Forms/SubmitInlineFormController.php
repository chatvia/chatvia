<?php

namespace App\Http\Controllers\Chat\Forms;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatSession;
use App\Http\Requests\Chat\Forms\SubmitInlineFormRequest;
use Domain\Agent\Models\Agent;
use Domain\Form\Actions\SubmitFormAction;
use Domain\Form\Data\SubmitFormData;
use Domain\Form\Enums\FormClaimFailure;
use Domain\Form\Exceptions\FormNotClaimableException;
use Domain\Form\Models\FormSubmission;
use Illuminate\Http\JsonResponse;

/**
 * Dashboard / Hosted Chat / Command Bar inline-form submit. Authenticates via
 * the `chat` middleware group's agent ({@see HostedChatAgent}). The form must
 * be the agent's and its conversation in the visitor's own chat session, so a
 * replayed form id cannot answer another visitor's form.
 */
class SubmitInlineFormController
{
    public function __invoke(
        SubmitInlineFormRequest $request,
        FormSubmission $formInstance,
        #[HostedChatAgent] ?Agent $agent,
        SubmitFormAction $action,
        HostedChatSession $session,
    ): JsonResponse {
        abort_unless($agent instanceof Agent && $formInstance->agent_id === $agent->id, 404);

        $conversation = $formInstance->conversation;
        abort_unless($conversation !== null && $conversation->session?->external_id === $session->find($agent), 404);

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
