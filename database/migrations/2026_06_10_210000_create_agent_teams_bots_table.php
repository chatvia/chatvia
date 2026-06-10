<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One Azure bot per agent (the agent's Microsoft Teams "channel"). The app id
 * is the bot's Microsoft App ID (a GUID — it is the audience of the JWT that
 * authenticates inbound activities, not a secret); the client secret is stored
 * encrypted (it mints the Bot Framework tokens our replies are sent with).
 * `webhook_secret` is the random value in the public messaging-endpoint URL —
 * that's how an incoming activity is routed to the right agent before its
 * JWT is verified.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_teams_bots', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('agent_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('app_id'); // Microsoft App ID (GUID) — JWT audience, not secret
            $table->text('app_secret'); // encrypted at the model layer
            $table->string('tenant_id')->nullable(); // single-tenant bots only; null = multi-tenant
            $table->string('webhook_secret', 64)->unique();
            $table->boolean('enabled')->default(true);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_teams_bots');
    }
};
