<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->string('external_id')->unique()->nullable()->after('id');
            $table->boolean('widget_enabled')->default(false)->index();
            $table->boolean('embed_enabled')->default(false)->index();
        });
    }

    public function down(): void {}
};
