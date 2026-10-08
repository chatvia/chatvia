<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Conversation erasure used to null `chat_messages.content` with a bulk update
 * that skipped the model hook keeping `content_bm25` (the BM25 search copy of
 * the text) in sync. Clear that leftover copy on every already-erased message.
 * Only rows whose text is gone are touched, so live messages keep their search
 * index; the change cannot be undone (the copy is the data being erased).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('UPDATE chat_messages SET content_bm25 = NULL WHERE content IS NULL AND content_bm25 IS NOT NULL');
    }

    public function down(): void
    {
        // Erased data is not restored.
    }
};
