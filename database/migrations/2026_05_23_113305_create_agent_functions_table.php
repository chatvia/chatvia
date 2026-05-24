<?php

use Domain\Agents\Models\Agent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_functions', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique();

            $table->foreignIdFor(Agent::class)->constrained()->cascadeOnDelete();

            // What the LLM sees
            $table->string('name', 64);
            $table->text('description');
            $table->jsonb('parameters_schema');

            // Webhook
            $table->text('webhook_url');
            $table->enum('webhook_method', ['POST', 'GET'])->default('POST');
            $table->text('webhook_headers')->nullable();           // encrypted JSON array
            $table->text('webhook_secret');                        // encrypted, platform-generated
            $table->enum('auth_type', ['none', 'bearer', 'header', 'hmac'])->default('none');
            $table->text('auth_value')->nullable();                // encrypted

            $table->unsignedSmallInteger('timeout_ms')->default(10000);
            $table->unsignedInteger('max_response_bytes')->default(65536);

            $table->boolean('enabled')->default(true)->index();

            // Observability
            $table->timestamp('last_invoked_at')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedBigInteger('invocation_count')->default(0);

            $table->timestamps();

            $table->unique(['agent_id', 'name']);
            $table->index(['agent_id', 'enabled']);
        });

        Schema::table('agents', function (Blueprint $table) {
            // Counter cache so ToolRegistry can short-circuit without a query
            // when an agent has no functions defined.
            $table->unsignedSmallInteger('functions_count')->default(0)->after('jwt_secret');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('functions_count');
        });

        Schema::dropIfExists('agent_functions');
    }
};
