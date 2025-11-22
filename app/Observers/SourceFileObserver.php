<?php

namespace App\Observers;

use App\Models\SourceFile;

class SourceFileObserver
{
    public function created(SourceFile $agentFile): void {}

    public function updated(SourceFile $agentFile): void {}

    public function deleted(SourceFile $agentFile): void
    {
        $agentFile->chunks()->delete();
    }

    public function restored(SourceFile $agentFile): void {}

    public function forceDeleted(SourceFile $agentFile): void
    {
        $this->deleted($agentFile);
    }
}
