<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Third-party credentials managed from the admin panel (Settings): OAuth apps,
 * API keys, webhook secrets. One row per settings group (`google-login`,
 * `stripe`, ...). `payload` holds every value of the group as one encrypted
 * JSON blob, so nothing is readable without APP_KEY. A saved value takes
 * priority over the matching .env value.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->text('payload')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_settings');
    }
};
