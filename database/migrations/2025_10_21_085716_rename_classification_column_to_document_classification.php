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
        Schema::table('agent_websites', function (Blueprint $table) {
            $table->renameColumn('classification', 'document_classification');
        });

        Schema::table('agent_texts', function (Blueprint $table) {
            $table->renameColumn('classification', 'document_classification');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
