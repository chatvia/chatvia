<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connector_oauth_clients', function (Blueprint $table) {
            $table->id();
            $table->text('auth_server_url')->unique();
            $table->string('client_id');
            $table->text('client_secret')->nullable();
            $table->text('registration_access_token')->nullable();
            $table->text('registration_client_uri')->nullable();
            $table->text('token_endpoint');
            $table->text('authorization_endpoint');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connector_oauth_clients');
    }
};
