<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add `cleared_at` to chat_conversations — a visitor-only "hide from my widget"
 * marker, distinct from `deleted_at` (soft delete).
 *
 * Background: the widget's "clear chats" button used to route through the same
 * destructive delete as the admin chat-log delete — it nulled every message's
 * content and soft-deleted the conversation, so the customer permanently lost
 * what the visitor was chatting about. That conflates a UI reset (the visitor
 * wants a clean slate) with Art. 17 erasure (which is the controller's call,
 * not a unilateral visitor power).
 *
 * `cleared_at` decouples the two: a visitor clear stamps `cleared_at` and flushes
 * Redis, but leaves the row alive (deleted_at IS NULL) with its message content
 * intact. The widget's session-rebuild query filters on `cleared_at IS NULL` so
 * the thread disappears from the visitor; every customer-side read (chat log,
 * analytics, topics) is untouched. Retention pruning still applies — cleared rows
 * keep their `expires_at` and `deleted_at IS NULL`, so the daily prune sweeps
 * them on schedule.
 *
 * Forward-only (the app is in production): nullable column, no backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->timestamp('cleared_at')->nullable()->after('deleted_by');
        });
    }

    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table): void {
            $table->dropColumn('cleared_at');
        });
    }
};
