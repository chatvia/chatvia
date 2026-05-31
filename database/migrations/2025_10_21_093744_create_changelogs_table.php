<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('changelogs', function (Blueprint $table) {
            $table->id();
            $table->string('version')->unique();
            $table->dateTime('release_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('changelogs');
    }
};
