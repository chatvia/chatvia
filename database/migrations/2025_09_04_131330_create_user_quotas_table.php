<?php

use App\Identity\Workspace\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('agents_limit')->default(0);
            $table->unsignedInteger('pages_limit')->default(0);
            $table->unsignedInteger('links_limit')->default(0);
            $table->unsignedInteger('minutes_limit')->default(0);
            $table->unsignedInteger('custom_domains_limit')->default(0);
            $table->unsignedInteger('workspace_users_limit')->default(0);
            $table->unsignedInteger('teams_limit')->default(0);

            $table->unsignedInteger('extra_agents_limit')->default(0);
            $table->unsignedInteger('extra_custom_domains_limit')->default(0);
            $table->unsignedInteger('extra_members_limit')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_quotas');
    }
};
