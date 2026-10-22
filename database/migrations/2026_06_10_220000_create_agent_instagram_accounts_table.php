<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_instagram_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('agent_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('page_access_token')->nullable();
            $table->string('page_id')->nullable();
            $table->string('ig_id')->nullable()->index();
            $table->string('username')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX agent_instagram_accounts_ig_id_enabled_unique ON agent_instagram_accounts (ig_id) WHERE is_enabled');
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_instagram_accounts');
    }
};
