<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_user', function (Blueprint $table): void {
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['owner', 'member'])->default('member');

            $table->primary(['workspace_id', 'user_id']);

            $table->unique('user_id');
        });

        DB::statement("CREATE UNIQUE INDEX workspace_user_one_owner ON workspace_user (workspace_id) WHERE role = 'owner'");
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_user');
    }
};
