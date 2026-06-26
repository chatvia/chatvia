<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_messenger_pages', function (Blueprint $table): void {
            $table->text('app_secret')->nullable()->change();
            $table->string('webhook_secret', 64)->nullable()->change();
            $table->unique('page_id');
        });
    }

    public function down(): void
    {
        Schema::table('agent_messenger_pages', function (Blueprint $table): void {
            $table->dropUnique(['page_id']);
            $table->string('webhook_secret', 64)->nullable(false)->change();
            $table->text('app_secret')->nullable(false)->change();
        });
    }
};
