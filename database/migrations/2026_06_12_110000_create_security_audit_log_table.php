<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_audit_log', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();

            $table->string('action');

            $table->string('target_type')->nullable();
            $table->string('target_id')->nullable();
            $table->string('target_label')->nullable();

            $table->string('ip')->nullable();
            $table->string('user_agent', 1024)->nullable();

            $table->jsonb('metadata')->nullable();
            $table->string('metadata_sha256', 64)->nullable();
            $table->boolean('metadata_truncated')->default(false);

            $table->enum('outcome', ['success', 'failure', 'denied'])->default('success');

            $table->timestamp('created_at')->useCurrent();

            $table->index(['workspace_id', 'id']);
            $table->index(['workspace_id', 'created_at']);
            $table->index(['workspace_id', 'action', 'id']);

            $table->index('user_id');
        });

        DB::statement('CREATE INDEX security_audit_log_created_at_brin ON security_audit_log USING brin (created_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('security_audit_log');
    }
};
