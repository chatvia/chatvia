<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('mcp_servers', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique();

            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();

            $table->string('connector_slug', 40)->nullable();

            $table->string('name', 64);
            $table->text('description')->nullable();
            $table->string('slug', 40);

            $table->text('url');
            $table->enum('transport', ['streamable_http', 'http_sse'])->default('streamable_http');

            $table->enum('auth_type', ['none', 'bearer', 'header'])->default('none');
            $table->text('auth_value')->nullable();
            $table->text('headers')->nullable();

            $table->unsignedInteger('timeout_ms')->default(300000);
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
            // Per-workspace OAuth client (bring-your-own apps, e.g. Salesforce
            // External Client App). Stored on the row — not the shared,
            // auth-server-keyed connector_oauth_clients table — so each workspace
            // keeps its own client_id and background refresh can recover it.
            $table->text('oauth_client_id')->nullable();
            $table->text('oauth_client_secret')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'slug']);
        });

        Schema::create('agent_mcp_server', function (Blueprint $table) {
            $table->foreignIdFor(Agent::class)->constrained()->cascadeOnDelete();
            $table->foreignId('mcp_server_id')
                ->constrained('mcp_servers')
                ->cascadeOnDelete();

            $table->unsignedInteger('invocation_count')->default(0);
            $table->timestamp('last_invoked_at')->nullable();

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
