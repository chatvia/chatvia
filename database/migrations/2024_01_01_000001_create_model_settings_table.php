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
            $table->morphs('model');
            $table->string('group')->index();
            $table->string('key');
            $table->text('value');
            $table->string('data_type');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_settings');
    }
};
