<?php

use Domain\Knowledge\Retrieval\Actions\ConfigureLexicalIndexesAction;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connector_tools', function (Blueprint $table) {
            $table->id();

            $table->string('connector_slug', 40);

            $table->string('name', 64);
            $table->text('description');
            $table->jsonb('input_schema');

            $table->jsonb('annotations')->nullable();

            $table->enum('scope', ['read', 'write'])->default('write');

            $table->enum('default_permission', ['allow', 'approval', 'blocked'])
                ->default('approval');

            $table->embedding('embedding')->nullable();
            $table->text('search_text')->nullable();

            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->unique(['connector_slug', 'name']);
        });

        app(ConfigureLexicalIndexesAction::class)->execute('connector_tools');

        Schema::create('mcp_tools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mcp_server_id')
                ->constrained('mcp_servers')
                ->cascadeOnDelete();

            $table->string('name', 64);
            $table->text('description');
            $table->jsonb('input_schema');

            $table->jsonb('annotations')->nullable();

            $table->enum('scope', ['read', 'write'])->default('write');

            $table->enum('permission', ['allow', 'approval', 'blocked'])
                ->default('approval');

            $table->embedding('embedding')->nullable();
            $table->text('search_text')->nullable();

            $table->timestamp('fetched_at');

            $table->timestamps();

            $table->unique(['mcp_server_id', 'name']);
        });

        app(ConfigureLexicalIndexesAction::class)->execute('mcp_tools');

        Schema::create('connector_tool_mcp_server', function (Blueprint $table) {
            $table->foreignId('mcp_server_id')
                ->constrained('mcp_servers')
                ->cascadeOnDelete();
            $table->foreignId('connector_tool_id')
                ->constrained('connector_tools')
                ->cascadeOnDelete();

            $table->enum('permission', ['allow', 'approval', 'blocked'])->default('approval');

            $table->unsignedBigInteger('invocation_count')->default(0);
            $table->timestamp('last_invoked_at')->nullable();

            $table->primary(['mcp_server_id', 'connector_tool_id']);
            $table->index('connector_tool_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connector_tool_mcp_server');
        Schema::dropIfExists('mcp_tools');
        Schema::dropIfExists('connector_tools');
    }
};
