<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocked_visitors', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            // The visitor's fingerprint (VisitorFingerprint); one block per
            // visitor, so blocking again updates the row.
            $table->string('visitor_key', 64);
            // The agent the attack was on, and what the visitor used then, so
            // the list says who the block is about. Never the network address.
            // Paired with workspace_id (composite key below): a block can never
            // point at another workspace's agent.
            $table->foreignId('agent_id')->nullable();
            $table->string('browser')->nullable();
            $table->string('os')->nullable();
            $table->foreignId('country_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason')->nullable();
            $table->foreignId('blocked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            // Null blocks until someone removes the block.
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'visitor_key']);
            $table->index(['workspace_id', 'id']);
        });

        // Deleting the agent keeps the block and clears only the agent link
        // (Postgres can null one column of a composite key).
        DB::statement('ALTER TABLE blocked_visitors ADD CONSTRAINT blocked_visitors_workspace_agent_foreign FOREIGN KEY (workspace_id, agent_id) REFERENCES agents (workspace_id, id) ON DELETE SET NULL (agent_id)');

        // The columns below are mostly NULL (expired context, optional blocker),
        // so their foreign-key indexes are partial.
        DB::statement('CREATE INDEX blocked_visitors_agent_idx ON blocked_visitors (agent_id) WHERE agent_id IS NOT NULL');
        DB::statement('CREATE INDEX blocked_visitors_country_idx ON blocked_visitors (country_id) WHERE country_id IS NOT NULL');
        DB::statement('CREATE INDEX blocked_visitors_blocked_by_idx ON blocked_visitors (blocked_by_user_id) WHERE blocked_by_user_id IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_visitors');
    }
};
