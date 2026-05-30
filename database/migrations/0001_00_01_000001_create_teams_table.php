<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // teams is created before users to break the circular FK (users.current_team_id
        // references teams). The teams.user_id FK is added in the trailing migration
        // 2025_12_09_204759_add_foreign_key_to_teams_user_id once users exists.
        Schema::create('teams', static function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->unsignedBigInteger('user_id');
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
