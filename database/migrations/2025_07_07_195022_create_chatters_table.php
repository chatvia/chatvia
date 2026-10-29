<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatters', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->enum('source', ['dashboard', 'api'])->default('dashboard');
            $table->string('locale', 5)->nullable();
            $table->string('profile_image_path')->nullable();
            $table->string('deactivation_reason')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->unique(['workspace_id', 'id']);
            $table->index(['workspace_id', 'name', 'id']);
        });

        DB::statement('CREATE UNIQUE INDEX chatters_workspace_email_unique ON chatters (workspace_id, lower(email))');

        Schema::create('agent_chatter', function (Blueprint $table): void {
            $table->foreignId('workspace_id');
            $table->foreignId('agent_id');
            $table->foreignId('chatter_id');

            $table->foreign(['workspace_id', 'agent_id'])->references(['workspace_id', 'id'])->on('agents')->cascadeOnDelete();
            $table->foreign(['workspace_id', 'chatter_id'])->references(['workspace_id', 'id'])->on('chatters')->cascadeOnDelete();

            $table->primary(['agent_id', 'chatter_id']);

            $table->index('chatter_id');
        });

        Schema::create('chatter_password_reset_tokens', function (Blueprint $table): void {
            $table->foreignId('chatter_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatter_password_reset_tokens');
        Schema::dropIfExists('agent_chatter');
        Schema::dropIfExists('chatters');
    }
};
