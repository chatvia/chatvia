<?php

namespace App\Http\Controllers\Chat\Forms;

use App\Http\Requests\Chat\Forms\SubmitInlineFormRequest;
use Domain\Form\Actions\SubmitFormAction;
use Domain\Form\Data\SubmitFormData;
use Domain\Form\Enums\FormClaimFailure;
use Domain\Form\Exceptions\FormNotClaimableException;
use Domain\Form\Models\FormSubmission;
use Illuminate\Http\JsonResponse;

/**
 * Dashboard / Hosted Chat / Command Bar inline-form submit. Authenticates via
 * the `chat` middleware group's current_agent(); the unguessable form instance
 * is bound to its agent + conversation here before persisting.
 */
class SubmitInlineFormController
{
    public function __invoke(
        SubmitInlineFormRequest $request,
        FormSubmission $formInstance,
        SubmitFormAction $action,
    ): JsonResponse {
        $agent = current_agent();
        abort_unless($agent !== null && $formInstance->agent_id === $agent->id, 404);

        $conversation = $formInstance->conversation;
        abort_unless($conversation !== null, 404);

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
