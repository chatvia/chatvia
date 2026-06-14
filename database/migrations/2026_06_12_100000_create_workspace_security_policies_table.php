<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Org-wide security & compliance settings — one row per workspace owner. The
 * keystone for the Enterprise Security suite: IP allowlisting, geo controls,
 * configurable data retention, SIEM webhooks, and the SCIM toggle all read
 * from here. Unlike per-agent/per-user `HasSettings`, this needs typed,
 * indexed, FK-able columns and clean before/after diffing for the audit log,
 * so it gets its own table. Resolved via `User::securityPolicy()` (firstOrCreate).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_security_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained('users')->cascadeOnDelete()->unique();

            // Retention overrides (days). null = inherit the plan/config ceiling.
            // A workspace may only SHORTEN retention, never exceed the plan.
            $table->unsignedInteger('retention_conversations_days')->nullable();
            $table->unsignedInteger('retention_leads_days')->nullable();
            $table->unsignedInteger('retention_audit_days')->nullable();
            $table->unsignedInteger('retention_analytics_days')->nullable();
            $table->unsignedInteger('anonymize_after_days')->nullable();

            // Network access control. `ip_allowlist_mode` is the master scope for
            // the whole network gate (IP allowlist + geo): off applies nothing,
            // dashboard guards the web app, dashboard_api also guards the API.
            $table->string('ip_allowlist_mode')->default('off'); // off | dashboard | dashboard_api
            $table->jsonb('ip_allowlist')->nullable();           // allowed IPs / CIDRs; empty = no IP restriction
            $table->string('geo_mode')->default('off');          // off | allow | deny
            $table->jsonb('geo_countries')->nullable();          // ISO-3166 alpha-2 codes

            // Outbound SIEM webhook (best-effort fan-out of the audit stream).
            $table->string('webhook_url')->nullable();
            $table->text('webhook_secret')->nullable();          // encrypted at the model layer
            $table->boolean('webhook_enabled')->default(false);
            $table->jsonb('webhook_events')->nullable();         // action allowlist; null = all
            $table->unsignedInteger('webhook_failures')->default(0);
            $table->timestamp('webhook_last_delivered_at')->nullable();

            // SCIM provisioning toggle.
            $table->boolean('scim_enabled')->default(false);

            // Platform-admin entitlement override for the whole Security suite.
            // null = inherit the plan entitlement, true = force-grant regardless
            // of plan (à-la-carte), false = force-revoke. Set ONLY by platform
            // staff (never the customer-facing policy form). The Pennant
            // `security` feature resolver reads this before falling back to plan.
            $table->boolean('security_override')->nullable();

            $table->timestamps(); // this row IS mutable
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_security_policies');
    }
};
