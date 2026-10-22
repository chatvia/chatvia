<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tenant. A workspace owns the agents, knowledge, tools, chatters and
 * settings; staff accounts belong to it through `workspace_user` (its own
 * migration, after users), where the owner is a role, so ownership can move
 * to another member.
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

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspaces');
    }
};
