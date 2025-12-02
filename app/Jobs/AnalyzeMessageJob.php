<?php

namespace App\Jobs;

use App\Models\ChatMessage;
use App\Services\Infrastructure\ToneAnalysis;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AnalyzeMessageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $messageId,
    ) {
        $this->onQueue('analytics');
    }

    public function handle(ToneAnalysis $toneAnalysis): void
    {
        $message = ChatMessage::find($this->messageId);

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
