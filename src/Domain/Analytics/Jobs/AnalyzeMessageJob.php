<?php

namespace Domain\Analytics\Jobs;

use App\TextAnalysis\ToneAnalysis;
use Domain\Chat\Models\ChatMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AnalyzeMessageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $externalMessageId,
    ) {
        $this->onQueue('analytics');
    }

    public function handle(ToneAnalysis $toneAnalysis): void
    {
        $message = ChatMessage::where('external_id', $this->externalMessageId)->first();

        if (! $message || $message->role !== 'user') {
            return;
        }

        if ($message->sentiment && $message->tone) {
            return;
        }

        $analysis = $toneAnalysis->analyze($message->content);

        $message->update([
            'sentiment' => $analysis['sentiment']->value,
            'tone' => $analysis['tone']->value,
        ]);
    }
}
