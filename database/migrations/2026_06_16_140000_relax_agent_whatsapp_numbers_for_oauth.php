<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_whatsapp_numbers', function (Blueprint $table): void {
            $table->dropUnique(['webhook_secret']);
            $table->text('app_secret')->nullable()->change();
            $table->string('webhook_secret', 64)->nullable()->change();
        });

        DB::statement('CREATE UNIQUE INDEX agent_whatsapp_numbers_phone_number_id_enabled_unique ON agent_whatsapp_numbers (phone_number_id) WHERE enabled');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS agent_whatsapp_numbers_phone_number_id_enabled_unique');

        Schema::table('agent_whatsapp_numbers', function (Blueprint $table): void {
            $table->text('app_secret')->nullable(false)->change();
            $table->string('webhook_secret', 64)->nullable(false)->change();
            $table->unique('webhook_secret');
        });
    }
};
