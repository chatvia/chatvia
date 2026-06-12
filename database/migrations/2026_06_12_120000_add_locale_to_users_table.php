<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Preferred UI language across the dashboard (Settings / user menu).
            // Null falls back to the request/browser locale — see the SetLocale
            // middleware and resources/js/i18n.
            $table->string('locale', 5)->nullable()->after('date_format');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('locale');
        });
    }
};
