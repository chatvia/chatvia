<?php

namespace App\Http\Controllers\Chat\Conversations;

use App\Http\Requests\Chat\CreateConversationRequest;
use Domain\Chat\Actions\CreateConversationAction;

final class ConversationCreateController
{
    /**
     * @return array{id: string}
     */
    public function __invoke(
        CreateConversationRequest $request,
        CreateConversationAction $createConversation,
    ): array {
        $agent = current_agent();

        abort_if(! $agent, 404);

        return [
            'id' => $createConversation->execute($request->toData($agent)),
        ];
    }
}
