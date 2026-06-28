<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('onboarded_at')->nullable()->after('last_login_at');
        });

        // Existing accounts are already up and running — backfill them as
        // onboarded so the welcome tour only ever greets people who sign up
        // after this feature ships, never the live customer base.
        DB::table('users')->whereNull('onboarded_at')->update(['onboarded_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('onboarded_at');
        });
    }
};
