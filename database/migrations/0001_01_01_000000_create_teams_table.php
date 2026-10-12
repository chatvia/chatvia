<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::create('teams', static function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            // The workspace the team belongs to; the FK is added with the
            // workspaces table (created after users).
            $table->unsignedBigInteger('workspace_id')->index();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
