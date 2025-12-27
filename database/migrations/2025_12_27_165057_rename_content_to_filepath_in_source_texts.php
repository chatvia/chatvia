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
        Schema::table('source_texts', function (Blueprint $table) {
            $table->renameColumn('content', 'excerpt');
            $table->string('excerpt')->change();
            $table->string('filepath')->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('source_texts', function (Blueprint $table) {
            //
        });
    }
};
