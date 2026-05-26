<?php

use App\Auth\Models\User;
use App\Teams\Models\Team;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('external_id', 20)->unique();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Team::class)->constrained()->cascadeOnDelete();
            $table->enum('type', ['knowledge_base', 'widget', 'search']);
            $table->string('name');
            $table->string('sub_domain')->nullable()->unique();
            $table->string('domain')->nullable()->unique();
            $table->text('system_instructions')->nullable();
            $table->boolean('is_public')->default(false);
            $table->boolean('collect_leads')->default(true);
            $table->boolean('analytics_enabled')->default(true);
            $table->json('metadata')->nullable();
            $table->enum('created_via', ['dashboard', 'api'])->default('dashboard')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
