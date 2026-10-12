<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The values managed in the admin panel (Settings): OAuth apps, API keys,
 * provider choices, email, brand and install-wide options. One row per
 * property of a settings class (spatie/laravel-settings): `group` is the
 * class, `payload` its JSON value. Secrets are encrypted with APP_KEY before
 * they are stored. The rows are created by the settings migrations in
 * database/settings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group');
            $table->string('name');
            $table->boolean('locked')->default(false);
            $table->json('payload');
            $table->timestamps();

            $table->unique(['group', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
