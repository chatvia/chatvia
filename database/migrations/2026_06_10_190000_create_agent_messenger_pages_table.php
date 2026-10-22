<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_messenger_pages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('agent_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('page_access_token');
            $table->string('page_id')->nullable()->index();
            $table->string('page_name')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX agent_messenger_pages_page_id_enabled_unique ON agent_messenger_pages (page_id) WHERE is_enabled');
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_messenger_pages');
    }
};
