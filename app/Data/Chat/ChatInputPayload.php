<?php

namespace App\Data\Chat;

use App\Models\Agent;
use App\Support\KnowledgeBase;
use Illuminate\Http\Request;
use Spatie\LaravelData\Data;

class ChatInputPayload extends Data
{
    public function __construct(
        public string $model,
        public string $message,
        public array $updated_message_info, // TODO: find a better name
        public ?string $message_for_llm,
        public ?int $member_id = null,
    ) {}

    public static function fromRequest(Request $request): static
    {
        $data = $request->all();
        $rag = app(KnowledgeBase::class);

        $agent = $request->has('playground') ? Agent::find($request->input('agent')) : current_agent();

        return new static(
            model: $data['model'],
            message: $data['message'],
            updated_message_info: $data['updated_message_info'] ?? [],
            message_for_llm: $rag->searchInKnowledgeBaseIfNeeded($agent, $request['message'])
        );
    }
}
