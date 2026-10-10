<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_security_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained('users')->cascadeOnDelete()->unique();

            $table->unsignedInteger('retention_conversations_days')->nullable();
            $table->unsignedInteger('retention_leads_days')->nullable();
            $table->unsignedInteger('retention_audit_days')->nullable();
            $table->unsignedInteger('retention_analytics_days')->nullable();
            $table->unsignedInteger('anonymize_after_days')->nullable();

            $table->string('ip_allowlist_mode')->default('off');
            $table->jsonb('ip_allowlist')->nullable();
            $table->string('geo_mode')->default('off');
            $table->jsonb('geo_countries')->nullable();

            $table->string('webhook_url')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->boolean('webhook_enabled')->default(false);
            $table->jsonb('webhook_events')->nullable();
            $table->unsignedInteger('webhook_failures')->default(0);
            $table->timestamp('webhook_last_delivered_at')->nullable();

            $table->boolean('scim_enabled')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_security_policies');
    }
};
