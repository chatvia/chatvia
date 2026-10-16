<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A staff account's membership in its workspace. The owner is a role here,
 * so ownership can move to another member. For now a person belongs to one
 * workspace (unique `user_id`).
 */
return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_user');
    }
};
