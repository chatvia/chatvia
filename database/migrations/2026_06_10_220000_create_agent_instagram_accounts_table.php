<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One Instagram professional account per agent (the agent's Instagram Direct
 * "channel"), connected via the Instagram API with Instagram Login — no
 * Facebook Page involved. The access token and app secret are stored
 * encrypted (the token can send DMs as the account; the secret authenticates
 * inbound webhooks). `webhook_secret` is the random value in the public
 * Callback URL — that's how an incoming event is routed to the right agent
 * before its signature is verified; it doubles as the verify token of Meta's
 * GET handshake.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_instagram_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('agent_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('access_token'); // encrypted at the model layer
            $table->text('app_secret'); // encrypted at the model layer
            $table->string('ig_id')->nullable(); // the professional account's Instagram id — used to ignore our own messages
            $table->string('username')->nullable();
            $table->string('webhook_secret', 64)->unique();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_instagram_accounts');
    }
};
