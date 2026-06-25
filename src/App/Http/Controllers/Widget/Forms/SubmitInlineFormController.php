<?php

namespace App\Http\Controllers\Widget\Forms;

use App\Http\Requests\Chat\Forms\SubmitInlineFormRequest;
use Domain\Form\Actions\SubmitFormAction;
use Domain\Form\Exceptions\FormNotClaimableException;
use Domain\Form\Models\FormSubmission;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Http\JsonResponse;

/**
 * Embedded-widget inline-form submit (anonymous visitor). Authenticates the
 * same way the widget stream does: current_agent_from_widget() + WidgetVerification,
 * which binds the form's conversation to this agent and (on a protected agent)
 * the signed-in chatter — so a visitor can't submit into another's thread.
 */
class SubmitInlineFormController
{
    public function __invoke(
        SubmitInlineFormRequest $request,
        FormSubmission $formInstance,
        SubmitFormAction $action,
        WidgetVerification $verification,
    ): JsonResponse {
        $agent = current_agent_from_widget();
        abort_unless($agent !== null && $formInstance->agent_id === $agent->id, 404);

        $conversation = $verification->verifyConversationId(
            $formInstance->conversation?->external_id,
            $agent,
            $request->attributes->get('widget_user'),
        );
        abort_unless($conversation !== null, 403);

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
