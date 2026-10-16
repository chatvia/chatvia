<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The tenant. A workspace owns the agents, knowledge, tools, chatters and
 * settings; staff accounts belong to it through `workspace_user`, where the
 * owner is a role, so ownership can move to another member. For now a person
 * belongs to one workspace (unique `user_id`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->string('name');
            // Admin-granted access to Spectra (the AI call log). Spectra is
            // install-wide, so a granted workspace sees every workspace's
            // AI calls; off unless staff grant it in the admin panel.
            $table->boolean('spectra_access')->default(false);
            $table->timestamps();
        });

        Schema::create('workspace_user', function (Blueprint $table): void {
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['owner', 'member'])->default('member');

            $table->primary(['workspace_id', 'user_id']);

            // One workspace per person, for now.
            $table->unique('user_id');
        });

        // Exactly one owner per workspace at a time.
        DB::statement("CREATE UNIQUE INDEX workspace_user_one_owner ON workspace_user (workspace_id) WHERE role = 'owner'");

        // teams are created before workspaces (users need teams for
        // current_team_id), so their workspace key is constrained here.
        Schema::table('teams', function (Blueprint $table): void {
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->dropForeign(['workspace_id']);
        });
        Schema::dropIfExists('workspace_user');
        Schema::dropIfExists('workspaces');
    }
};
