<?php

use Domain\Agents\Models\Agent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_mcp_servers', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique();

            $table->foreignIdFor(Agent::class)->constrained()->cascadeOnDelete();

            // If this row was provisioned by a connector preset (config/connectors.php),
            // the slug is recorded here for display + bookkeeping. NULL for raw user-
            // entered MCP servers (which the connector layer hides for now).
            $table->string('connector_slug', 40)->nullable()->index();

            $table->string('name', 64);                   // display label
            $table->string('slug', 40);                   // used as tool-name prefix `mcp__{slug}__...`

            $table->text('url');                          // MCP endpoint
            $table->enum('transport', ['streamable_http', 'http_sse'])->default('streamable_http');

            // Auth — same encryption-at-rest approach as AgentFunction.
            $table->enum('auth_type', ['none', 'bearer', 'header'])->default('none');
            $table->text('auth_value')->nullable();       // encrypted
            $table->text('headers')->nullable();          // encrypted JSON

            $table->unsignedSmallInteger('timeout_ms')->default(30000);
            $table->boolean('enabled')->default(true)->index();

            // Observability + catalog snapshot
            $table->timestamp('catalog_cached_at')->nullable();
            $table->unsignedSmallInteger('catalog_tools_count')->default(0);
            $table->timestamp('last_invoked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedBigInteger('invocation_count')->default(0);

            $table->text('oauth_authorization_server')->nullable()->after('headers');
            $table->text('oauth_token_endpoint')->nullable()->after('oauth_authorization_server');

            // Long-lived refresh token (encrypted). Access token reuses the
            // existing `auth_value` column so the McpClient bearer code path
            // stays uniform between token-paste and OAuth connectors.
            $table->text('oauth_refresh_token')->nullable()->after('oauth_token_endpoint');
            $table->timestamp('oauth_expires_at')->nullable()->after('oauth_refresh_token');
            $table->string('oauth_scopes', 255)->nullable()->after('oauth_expires_at');

            $table->timestamps();

            $table->unique(['agent_id', 'slug']);
            $table->index(['agent_id', 'enabled']);
        });

        Schema::table('agents', function (Blueprint $table) {
            // Counter cache — mirrors agents.functions_count. Lets ToolRegistry
            // short-circuit to [] for agents with no MCP servers, zero DB cost.
            $table->unsignedSmallInteger('mcp_servers_count')->default(0)->after('functions_count');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('mcp_servers_count');
        });

        Schema::dropIfExists('agent_mcp_servers');
    }
};
