<?php

use Domain\Agent\Models\Agent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('mcp_servers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();

            // Null for a global built-in server. A workspace's own servers go
            // with the workspace.
            $table->foreignId('workspace_id')->nullable()->constrained()->cascadeOnDelete();

            $table->enum('category', ['custom', 'integration', 'builtin', 'analytics']);

            $table->string('connector_slug', 40)->nullable()->index();

            $table->boolean('uses_central_catalog')->default(false);

            $table->string('name', 64);
            $table->text('description')->nullable();
            $table->string('slug', 40);

            $table->text('url');
            $table->enum('transport', ['streamable_http', 'http_sse'])->default('streamable_http');

            $table->enum('auth_type', ['none', 'bearer', 'header'])->default('none');
            $table->text('auth_value')->nullable();
            $table->text('headers')->nullable();

            $table->unsignedInteger('timeout_ms')->default(60000);
            $table->boolean('is_enabled')->default(true);

            $table->timestamp('catalog_cached_at')->nullable();
            $table->unsignedSmallInteger('catalog_tools_count')->default(0);
            $table->timestamp('last_invoked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedBigInteger('invocation_count')->default(0);

            $table->text('oauth_authorization_server')->nullable();
            $table->text('oauth_token_endpoint')->nullable();
            $table->text('oauth_refresh_token')->nullable();
            $table->timestamp('oauth_expires_at')->nullable();
            $table->string('oauth_scopes', 255)->nullable();

            $table->text('oauth_client_id')->nullable();
            $table->text('oauth_client_secret')->nullable();

            $table->timestamps();

            $table->unique(['workspace_id', 'slug']);
        });

        DB::statement('CREATE UNIQUE INDEX mcp_servers_global_slug_unique ON mcp_servers (slug) WHERE workspace_id IS NULL');

        Schema::create('agent_mcp_server', function (Blueprint $table): void {
            $table->foreignIdFor(Agent::class)->constrained()->cascadeOnDelete();
            $table->foreignId('mcp_server_id')
                ->constrained('mcp_servers')
                ->cascadeOnDelete();

            $table->unsignedBigInteger('invocation_count')->default(0);
            $table->timestamp('last_invoked_at')->nullable();
            // Per-agent "always on": the connector's tools ship on every turn
            // instead of only when the relevance router picks them.
            $table->boolean('always_available')->default(false);

            $table->primary(['agent_id', 'mcp_server_id']);

            $table->index('mcp_server_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_mcp_server');
        Schema::dropIfExists('mcp_servers');
    }
};
