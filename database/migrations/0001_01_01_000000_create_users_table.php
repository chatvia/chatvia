<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            // Filament panel multi-factor auth (app authenticator + recovery
            // codes). Stored encrypted by the InteractsWithAppAuthentication*
            // traits — separate from Fortify's two_factor_* columns above, which
            // back the Inertia/Vue dashboard's 2FA.
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
            $table->enum('type', ['admin', 'workspace', 'employee', 'chatter'])->default('workspace')->index();
            $table->enum('source', ['dashboard', 'api'])->default('dashboard'); // two-value enum — never filtered, so no index
            $table->rememberToken();

            $table->foreignId('workspace_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('current_team_id')
                ->nullable()
                ->index()
                ->constrained('teams')
                ->nullOnDelete();

            $table->index(['workspace_id', 'type']);

            $table->ipAddress('registered_ip')->nullable();
            $table->string('registered_country_code', 2)->nullable(); // ISO-2

            $table->string('domain')->nullable();

            $table->string('profile_image_path')->nullable();

            $table->string('deactivation_reason')->nullable();

            $table->timestamp('deactivated_at')->nullable();
            $table->timestamp('last_login_at')->nullable();

            // Preferred date display format across the dashboard
            // (Settings → Date & time). Null falls back to the ISO default —
            // see Support\Enums\User\DateFormat.
            $table->string('date_format')->nullable();

            // Preferred UI locale (e.g. `en`, `de`, `fr`); null falls back to the
            // app default. Shared to the SPA via auth.user.
            $table->string('locale', 5)->nullable();

            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable(); // covered by the composite below
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();

            $table->index(['user_id', 'last_activity']);
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
