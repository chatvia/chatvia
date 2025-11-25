<?php

use App\Data\Chat\ChatInputPayload;
use App\Jobs\StoreChatMessageJob;
use App\Models\Agent;
use App\Models\ChatSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

describe('StoreChatMessageJob', function () {
    beforeEach(function () {
        // Mock IP2Country HTTP requests
        Http::fake([
            '*' => Http::response([
                'country_code' => 'US',
            ], 200),
        ]);
    });
    it('saves device type, browser and OS information from user agent when creating a session', function () {
        $agent = Agent::factory()->create();

        $sessionId = Str::ulid()->toBase32();
        $messageId = Str::ulid()->toBase32();
        $conversationId = Str::ulid()->toBase32();

        $payload = ChatInputPayload::from([
            'model' => 'gpt-4',
            'message' => 'Hello, this is a test message',
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ]);

        $job = new StoreChatMessageJob(
            agent: $agent,
            sessionId: $sessionId,
            role: 'user',
            conversationId: $conversationId,
            title: 'Test Conversation',
            messageId: $messageId,
            payload: $payload
        );

        $job->handle(
            app(\App\Services\Infrastructure\SentimentAnalysis::class),
            app(\App\Services\TopicAnalysis::class)
        );

        $session = ChatSession::find($sessionId);

        expect($session)->not->toBeNull()
            ->and($session->user_agent)->toBe('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36')
            ->and($session->device_type)->toBe('Desktop')
            ->and($session->browser)->toBe('Chrome')
            ->and($session->os)->toBe('Windows 10.0');
    });

    it('updates device info if session exists but device info is missing', function () {
        $agent = Agent::factory()->create();

        $sessionId = Str::ulid()->toBase32();

        // Create session without device info
        ChatSession::create([
            'id' => $sessionId,
            'agent_id' => $agent->id,
            'ip_address' => '192.168.1.1',
            'user_agent' => null,
        ]);

        $messageId = Str::ulid()->toBase32();
        $conversationId = Str::ulid()->toBase32();

        $payload = ChatInputPayload::from([
            'model' => 'gpt-4',
            'message' => 'Hello, this is a test message',
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
        ]);

        $job = new StoreChatMessageJob(
            agent: $agent,
            sessionId: $sessionId,
            role: 'user',
            conversationId: $conversationId,
            title: 'Test Conversation',
            messageId: $messageId,
            payload: $payload
        );

        $job->handle(
            app(\App\Services\Infrastructure\SentimentAnalysis::class),
            app(\App\Services\TopicAnalysis::class)
        );

        $session = ChatSession::find($sessionId);

        expect($session)->not->toBeNull()
            ->and($session->device_type)->toBe('iPhone')
            ->and($session->browser)->toBe('Safari')
            ->and($session->os)->toBe('iOS');
    });

    it('handles null user agent gracefully', function () {
        $agent = Agent::factory()->create();

        $sessionId = Str::ulid()->toBase32();
        $messageId = Str::ulid()->toBase32();
        $conversationId = Str::ulid()->toBase32();

        $payload = ChatInputPayload::from([
            'model' => 'gpt-4',
            'message' => 'Hello, this is a test message',
            'ip_address' => '192.168.1.1',
            'user_agent' => null,
        ]);

        $job = new StoreChatMessageJob(
            agent: $agent,
            sessionId: $sessionId,
            role: 'user',
            conversationId: $conversationId,
            title: 'Test Conversation',
            messageId: $messageId,
            payload: $payload
        );

        $job->handle(
            app(\App\Services\Infrastructure\SentimentAnalysis::class),
            app(\App\Services\TopicAnalysis::class)
        );

        $session = ChatSession::find($sessionId);

        expect($session)->not->toBeNull()
            ->and($session->user_agent)->toBeNull()
            ->and($session->device_type)->toBeNull()
            ->and($session->browser)->toBeNull()
            ->and($session->os)->toBeNull();
    });

    it('parses mobile device user agents correctly', function () {
        $agent = Agent::factory()->create();

        $sessionId = Str::ulid()->toBase32();
        $messageId = Str::ulid()->toBase32();
        $conversationId = Str::ulid()->toBase32();

        $payload = ChatInputPayload::from([
            'model' => 'gpt-4',
            'message' => 'Hello from Android',
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0 (Linux; Android 13; SM-S908B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Mobile Safari/537.36',
        ]);

        $job = new StoreChatMessageJob(
            agent: $agent,
            sessionId: $sessionId,
            role: 'user',
            conversationId: $conversationId,
            title: 'Test Conversation',
            messageId: $messageId,
            payload: $payload
        );

        $job->handle(
            app(\App\Services\Infrastructure\SentimentAnalysis::class),
            app(\App\Services\TopicAnalysis::class)
        );

        $session = ChatSession::find($sessionId);

        expect($session)->not->toBeNull()
            ->and($session->device_type)->toBe('Android Phone')
            ->and($session->browser)->toBe('Chrome')
            ->and($session->os)->toBe('Android 13');
    });

    it('parses tablet device user agents correctly', function () {
        $agent = Agent::factory()->create();

        $sessionId = Str::ulid()->toBase32();
        $messageId = Str::ulid()->toBase32();
        $conversationId = Str::ulid()->toBase32();

        $payload = ChatInputPayload::from([
            'model' => 'gpt-4',
            'message' => 'Hello from iPad',
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
        ]);

        $job = new StoreChatMessageJob(
            agent: $agent,
            sessionId: $sessionId,
            role: 'user',
            conversationId: $conversationId,
            title: 'Test Conversation',
            messageId: $messageId,
            payload: $payload
        );

        $job->handle(
            app(\App\Services\Infrastructure\SentimentAnalysis::class),
            app(\App\Services\TopicAnalysis::class)
        );

        $session = ChatSession::find($sessionId);

        expect($session)->not->toBeNull()
            ->and($session->device_type)->toBe('iPad')
            ->and($session->browser)->toBe('Safari')
            ->and($session->os)->toBe('iOS');
    });
});
