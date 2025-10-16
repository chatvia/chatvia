<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_files', function (Blueprint $table) {
            $table->renameColumn('chunks', 'expected_chunks');
        });

        Schema::table('agent_texts', function (Blueprint $table) {
            $table->renameColumn('chunks', 'expected_chunks');
        });

        Schema::table('agent_websites', function (Blueprint $table) {
            $table->renameColumn('chunks', 'expected_chunks');
        });
    }

    public function down(): void
    {

    }
};
