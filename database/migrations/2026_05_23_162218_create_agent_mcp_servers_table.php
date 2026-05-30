<?php

use App\Auth\Models\User;
use Domain\Agents\Models\Agent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Account-level MCP server definitions. One Linear MCP per customer,
        // attachable to any number of their agents via the `agent_mcp_server`
        // pivot. `user_id` names the owning account.
        //
        // Built-in connectors (`connector_slug IS NOT NULL`) live here too,
        // identified by the catalog slug; the public API only exposes the
        // raw user-defined ones (connector_slug NULL).
        Schema::create('mcp_servers', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique();

            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();

            $table->string('connector_slug', 40)->nullable()->index();

            $table->string('name', 64);                   // display label
            $table->string('slug', 40);                   // tool-name prefix `mcp__{slug}__...`

            $table->text('url');                          // MCP endpoint
            $table->enum('transport', ['streamable_http', 'http_sse'])->default('streamable_http');

            // Auth — encrypted at rest in the model casts.
            $table->enum('auth_type', ['none', 'bearer', 'header'])->default('none');
            $table->text('auth_value')->nullable();
            $table->text('headers')->nullable();

            $table->unsignedSmallInteger('timeout_ms')->default(30000);
            $table->boolean('enabled')->default(true)->index();

            // Observability + catalog snapshot
            $table->timestamp('catalog_cached_at')->nullable();
            $table->unsignedSmallInteger('catalog_tools_count')->default(0);
            $table->timestamp('last_invoked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedBigInteger('invocation_count')->default(0);

            // OAuth fields used by ConnectorOAuthFlow for catalog connectors
            // that require an OAuth handshake (Linear, GitHub, etc.).
            $table->text('oauth_authorization_server')->nullable();
            $table->text('oauth_token_endpoint')->nullable();
            $table->text('oauth_refresh_token')->nullable();
            $table->timestamp('oauth_expires_at')->nullable();
            $table->string('oauth_scopes', 255)->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'slug']);
        });

        // Pivot — which agents have this MCP attached. Pure junction:
        // presence of a row means "this agent can use this MCP." To pause,
        // detach. Laravel-conventional pivot name: alphabetical singular
        // of the two related models.
        Schema::create('agent_mcp_server', function (Blueprint $table) {
            $table->foreignIdFor(Agent::class)->constrained()->cascadeOnDelete();
            $table->foreignId('mcp_server_id')
                ->constrained('mcp_servers')
                ->cascadeOnDelete();

            $table->primary(['agent_id', 'mcp_server_id']);
            // Reverse direction: "which agents have this MCP attached?"
            $table->index('mcp_server_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_mcp_server');
        Schema::dropIfExists('mcp_servers');
    }
};
