<?php

namespace App\Http\Controllers\Chat\Forms;

use App\Http\Requests\Chat\Forms\SubmitInlineFormRequest;
use Domain\Form\Actions\SubmitFormAction;
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
            $result = $action->execute(
                $formInstance,
                $conversation,
                $request->fieldValues(),
                $request->ip(),
                $request->userAgent(),
                $request->requestId(),
                $request->assistantMessageId(),
            );
        } catch (FormNotClaimableException $e) {
            return response()->json([
                'status' => $e->status === 410 ? 'expired' : 'already_submitted',
                'message' => $e->getMessage(),
            ], $e->status);
        }

        return response()->json($result);
    }
}
