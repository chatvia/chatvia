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
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('kind', [
                'handoff_requested',
                'source_trained',
                'source_training_failed',
                'lead_captured',
                'attack_blocked',
                'guardrail_skipped',
                'security_settings_changed',
                'new_device_sign_in',
                'message_limit_warning',
                'message_limit_reached',
                'rate_limit_exceeded',
                'eval_run_finished',
                'eval_regressed',
                'data_export_ready',
                'ai_model_unavailable',
            ]);
            $table->enum('category', ['handoffs', 'security', 'training', 'leads', 'usage', 'reports']);
            $table->jsonb('content');
            $table->string('group_key')->nullable();
            $table->unsignedInteger('count')->default(1);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'updated_at']);
            $table->index('updated_at');
        });

        DB::statement('CREATE INDEX user_notifications_unread_idx ON user_notifications (user_id) WHERE read_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX user_notifications_open_group_unique ON user_notifications (group_key, user_id) WHERE read_at IS NULL AND group_key IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
