<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            Schema::table('agent_texts', function (Blueprint $table) {
                rescue(fn() => $table->renameColumn('text', 'content'));
            });
        } catch (Exception $e) {}
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_texts', function (Blueprint $table) {
            //
        });
    }
};
