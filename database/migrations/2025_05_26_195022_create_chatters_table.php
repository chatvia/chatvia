<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chatters: a workspace's end-users, who only sign in to its agents' hosted
 * chat and widget. They live apart from `users` (staff), so the same email
 * can be a chatter in several workspaces and a staff account at once.
 */
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
            // Null until the chatter picks one; an invite-only or sign-in-link
            // chatter never has a password.
            $table->string('password')->nullable();
            $table->enum('source', ['dashboard', 'api'])->default('dashboard');
            $table->string('locale', 5)->nullable();
            $table->string('profile_image_path')->nullable();
            $table->string('deactivation_reason')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            // Target of the (workspace_id, chatter_id) keys on chat sessions and
            // agent grants; also serves lookups by workspace.
            $table->unique(['workspace_id', 'id']);
        });

        // One chatter per email inside a workspace, compared without case.
        DB::statement('CREATE UNIQUE INDEX chatters_workspace_email_unique ON chatters (workspace_id, lower(email))');

        // Which agents a chatter may use. Both keys carry the workspace, so the
        // database refuses a grant that pairs an agent and a chatter of
        // different workspaces.
        Schema::create('agent_chatter', function (Blueprint $table): void {
            $table->foreignId('workspace_id');
            $table->foreignId('agent_id');
            $table->foreignId('chatter_id');

            $table->foreign(['workspace_id', 'agent_id'])->references(['workspace_id', 'id'])->on('agents')->cascadeOnDelete();
            $table->foreign(['workspace_id', 'chatter_id'])->references(['workspace_id', 'id'])->on('chatters')->cascadeOnDelete();

            $table->primary(['agent_id', 'chatter_id']);

            $table->index('chatter_id');
        });

        // Reset tokens are keyed by the chatter, never by email: the same email
        // can belong to chatters of different workspaces.
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
