<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_settings', function (Blueprint $table) {
            $table->id();

            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->string('group');
            $table->string('key');
            $table->text('value');
            $table->string('data_type');
            $table->timestamps();

            // One value per model, group and key: saves are upserts on it.
            $table->unique(['model_type', 'model_id', 'group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_settings');
    }
};
