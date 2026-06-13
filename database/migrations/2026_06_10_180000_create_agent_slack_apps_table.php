<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One Slack app per agent (the agent's Slack "channel"). The bot token and
 * signing secret are stored encrypted (the token can post on the workspace's
 * behalf; the secret authenticates inbound events). `webhook_secret` is the
 * random value in the public Events API Request URL — that's how an incoming
 * event is routed to the right agent before its signature is verified.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_slack_apps', function (Blueprint $table): void {
            $table->id();
            $table->uuid('external_id')->unique();
            $table->foreignId('agent_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('bot_token'); // encrypted at the model layer
            $table->text('signing_secret'); // encrypted at the model layer
            $table->string('bot_user_id')->nullable(); // the bot's Slack user id (U…) — used to strip @mentions and ignore our own messages
            $table->string('team_name')->nullable();
            $table->string('webhook_secret', 64)->unique();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_slack_apps');
    }
};
