<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_teams_bots', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('agent_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('app_id');
            $table->text('app_secret');
            $table->string('tenant_id')->nullable();
            $table->string('webhook_secret', 64)->unique();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_teams_bots');
    }
};
