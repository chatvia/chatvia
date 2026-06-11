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
            // Bare columns instead of morphs(): the (model_type, model_id)
            // index morphs() creates is a strict prefix of the 3-column
            // composite below — pure write amplification.
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->string('group')->index();
            $table->string('key');
            $table->text('value');
            $table->string('data_type');
            $table->timestamps();

            $table->index(['model_type', 'model_id', 'group']);
            $table->index(['group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_settings');
    }
};
