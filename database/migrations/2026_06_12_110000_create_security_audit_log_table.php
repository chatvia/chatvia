<?php

use Domain\Security\Events\SecurityEventOccurred;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable, workspace-scoped security audit log — the SOC 2 backbone. A
 * structural clone of `agent_activity`: one parametric event
 * ({@see SecurityEventOccurred}) writes one insert-only
 * row per audited operation (auth, membership, config, data) discriminated by
 * `action`.
 *
 * The causer is denormalized (`user_id` nulls on delete, but `user_name` /
 * `user_email` survive it) so a row stays readable after the actor is removed.
 * `metadata_sha256` hashes the RAW pre-redaction payload for tamper-evidence;
 * the stored `metadata` is redacted + size-capped. Insert-only (no updated_at).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_audit_log', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('workspace_id')->constrained('users')->cascadeOnDelete(); // tenant scope

            // The actor. FK nulls on delete; name/email survive it so a row
            // stays attributable after the user is removed. Null user = system.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();

            $table->string('action'); // backed by SecurityAuditAction

            // What the action operated on (role, member, api key, agent, ...).
            $table->string('target_type')->nullable();
            $table->string('target_id')->nullable();
            $table->string('target_label')->nullable();

            // Request context captured at the boundary (auth listeners run on
            // the web thread; queued contexts leave these null).
            $table->string('ip')->nullable();
            $table->string('user_agent', 1024)->nullable();

            // Payload (redacted + size-capped; sha256 over the RAW payload).
            $table->jsonb('metadata')->nullable();
            $table->string('metadata_sha256', 64)->nullable();
            $table->boolean('metadata_truncated')->default(false);

            $table->enum('outcome', ['success', 'failure', 'denied'])->default('success');

            $table->timestampTz('created_at')->useCurrent(); // immutable; no updated_at

            // Postgres does not auto-index FK columns. The (…, id) composites
            // serve the viewer's ORDER BY id DESC walks.
            $table->index(['workspace_id', 'id']);
            $table->index(['workspace_id', 'created_at']);
            $table->index(['workspace_id', 'action', 'id']);
            $table->index('action');
            $table->index('user_id');
        });

        // BRIN for the retention prune's created_at sweep.
        DB::statement('CREATE INDEX security_audit_log_created_at_brin ON security_audit_log USING brin (created_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('security_audit_log');
    }
};
