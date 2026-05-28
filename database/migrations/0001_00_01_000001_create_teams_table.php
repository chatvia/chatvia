<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('teams', static function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->unsignedInteger('user_id');
            $table->string('name');
            // false = chat-only team: members can't open the dashboard, and its
            // roles carry agent access only (no dashboard permissions).
            $table->boolean('can_access_dashboard')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(Config::get('teams.tables.teams', 'teams'));
    }
};
