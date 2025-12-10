<?php

use App\Models\User;
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
            $table->unsignedInteger('agents_limit')->index()->default(0);
            $table->unsignedInteger('pages_limit')->index()->default(0);
            $table->unsignedInteger('links_limit')->index()->default(0);
            $table->unsignedInteger('minutes_limit')->index()->default(0);
            $table->unsignedInteger('custom_domains_limit')->index()->default(0);
            $table->unsignedInteger('members_limit')->index()->default(0);
            $table->unsignedInteger('teams_limit')->index()->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_quotas');
    }
};
