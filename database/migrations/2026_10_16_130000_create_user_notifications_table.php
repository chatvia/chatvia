<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            // One person's inbox: deleting the account deletes its notifications.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 64);
            $table->string('category', 32);
            // What the dashboard needs to write the line in the reader's own
            // language: the agent, a subject, who did it, and the link.
            $table->jsonb('content');
            // Notifications that share a key gather into one unread row
            // ("5 sources finished training"); null never gathers.
            $table->string('group_key')->nullable();
            $table->unsignedInteger('count')->default(1);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
            $table->index('updated_at');
        });

        // The unread badge counts only unread rows.
        DB::statement('CREATE INDEX user_notifications_unread_idx ON user_notifications (user_id) WHERE read_at IS NULL');
        // At most one unread row per group, so gathering is one INSERT … ON CONFLICT.
        DB::statement('CREATE UNIQUE INDEX user_notifications_open_group_unique ON user_notifications (user_id, group_key) WHERE read_at IS NULL AND group_key IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
