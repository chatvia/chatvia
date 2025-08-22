<?php

namespace Tests\Unit\Support\Chat;

use App\Contracts\ChatMemoryInterface;
use App\Data\Chat\ChatInputPayload;
use App\Data\Chat\ChatOutputPayload;
use App\Jobs\StoreChatMessageJob;
use App\Models\Agent;
use App\Models\Member;
use App\Services\Memory\ChatMemory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Tests\TestCase;

class ChatMemoryTest extends TestCase
{
    use RefreshDatabase;

    private ChatMemory $chatMemory;

    private Agent $agent;

    private string $sessionId;

    private string $conversationId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = Agent::factory()->create();
        $this->sessionId = 'test-session-'.uniqid();
        $this->conversationId = 'conv-'.uniqid();

        $this->chatMemory = new ChatMemory;
    }

    protected function tearDown(): void
    {
        // Clear any cache between tests
        try {
            $this->chatMemory
                ->setAgent($this->agent)
                ->setSessionId($this->sessionId)
                ->cacheFlush();
        } catch (\Throwable $e) {
            // Ignore cache clearing errors during teardown
        }

        parent::tearDown();
    }

    public function test_implements_chat_memory_interface(): void
    {
        $this->assertInstanceOf(ChatMemoryInterface::class, $this->chatMemory);
    }

    public function test_fluent_configuration_methods(): void
    {
        $result = $this->chatMemory
            ->setAgent($this->agent)
            ->setSessionId($this->sessionId)
            ->setConversationId($this->conversationId);

        $this->assertInstanceOf(ChatMemory::class, $result);
    }

    public function test_throws_exception_when_conversation_id_required_but_not_set(): void
    {
        $this->chatMemory
            ->setAgent($this->agent)
            ->setSessionId($this->sessionId);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Conversation ID must be set before performing this operation');

        $this->chatMemory->updateConversationTitle('New Title');
    }

    public function test_throws_exception_when_agent_not_set_during_initialization(): void
    {
        $this->chatMemory->setSessionId($this->sessionId);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Agent must be set before initialization');

        $this->chatMemory->createConversation('Test Conversation');
    }

    public function test_throws_exception_when_session_id_not_set_during_initialization(): void
    {
        $this->chatMemory->setAgent($this->agent);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Session ID must be set before initialization');

        $this->chatMemory->createConversation('Test Conversation');
    }

    public function test_create_conversation(): void
    {
        $this->chatMemory
            ->setAgent($this->agent)
            ->setSessionId($this->sessionId);

        $conversationId = $this->chatMemory->createConversation('Test Conversation');

        $this->assertIsString($conversationId);
        $this->assertNotEmpty($conversationId);
    }

    public function test_update_conversation_title(): void
    {
        $this->chatMemory
            ->setAgent($this->agent)
            ->setSessionId($this->sessionId)
            ->setConversationId($this->conversationId);

        $this->chatMemory->createConversation('Original Title');
        $this->chatMemory->updateConversationTitle('Updated Title');

        // This should not throw any exceptions
        $this->assertTrue(true);
    }

    public function test_conversation_exists(): void
    {
        $this->chatMemory
            ->setAgent($this->agent)
            ->setSessionId($this->sessionId);

        // Create a conversation and get its ID
        $conversationId = $this->chatMemory->createConversation('Test Conversation');

        // Set the conversation ID and check if it exists
        $this->chatMemory->setConversationId($conversationId);
        $this->assertTrue($this->chatMemory->conversationExists());

        // Test with non-existent conversation
        $this->chatMemory->setConversationId('non-existent-conv');
        $this->assertFalse($this->chatMemory->conversationExists());
    }

    public function test_deprecated_conversation_exist_method(): void
    {
        $this->chatMemory
            ->setAgent($this->agent)
            ->setSessionId($this->sessionId);

        // Create a conversation and get its ID
        $conversationId = $this->chatMemory->createConversation('Test Conversation');

        // Set the conversation ID and check if it exists using deprecated method
        $this->chatMemory->setConversationId($conversationId);
        $this->assertTrue($this->chatMemory->conversationExist());

        // Test with non-existent conversation
        $this->chatMemory->setConversationId('non-existent-conv');
        $this->assertFalse($this->chatMemory->conversationExist());
    }

    public function test_delete_conversation(): void
    {
        $this->chatMemory
            ->setAgent($this->agent)
            ->setSessionId($this->sessionId);

        $conversationId = $this->chatMemory->createConversation('Test Conversation');
        $this->chatMemory->setConversationId($conversationId);

        $this->assertTrue($this->chatMemory->conversationExists());

        $this->chatMemory->deleteConversation();
        $this->assertFalse($this->chatMemory->conversationExists());
    }

    public function test_add_message(): void
    {
        $this->chatMemory
            ->setAgent($this->agent)
            ->setSessionId($this->sessionId);

        $conversationId = $this->chatMemory->createConversation('Test Conversation');
        $this->chatMemory->setConversationId($conversationId);

        $messageId = $this->chatMemory->addMessage(
            role: 'user',
            message: 'Hello, how are you?',
            model: 'gpt-4'
        );

        $this->assertIsString($messageId);
        $this->assertNotEmpty($messageId);
    }

    public function test_add_message_via_input_payload(): void
    {
        Queue::fake();

        $this->chatMemory
            ->setAgent($this->agent)
            ->setSessionId($this->sessionId);

        $conversationId = $this->chatMemory->createConversation('Test Conversation');
        $this->chatMemory->setConversationId($conversationId);

        $payload = ChatInputPayload::from([
            'model' => 'gpt-4',
            'message' => 'Hello, world!',
            'message_for_llm' => 'Hello, world!',
            'language' => 'en',
        ]);

        $messageId = $this->chatMemory->addMessageViaPayload($payload);

        $this->assertIsString($messageId);
        $this->assertNotEmpty($messageId);

        Queue::assertPushed(StoreChatMessageJob::class, function ($job) {
            return $job->agent->is($this->agent);
        });
    }

    public function test_add_message_via_output_payload(): void
    {
        Queue::fake();

        $this->chatMemory
            ->setAgent($this->agent)
            ->setSessionId($this->sessionId);

        $conversationId = $this->chatMemory->createConversation('Test Conversation');
        $this->chatMemory->setConversationId($conversationId);

        $payload = ChatOutputPayload::from([
            'message_id' => 'msg-123',
            'role' => 'assistant',
            'message' => 'Hello back!',
            'model' => 'gpt-4',
            'generation_id' => 'gen-123',
            'language' => 'en',
        ]);

        $messageId = $this->chatMemory->addMessageViaPayload($payload);

        $this->assertEquals('msg-123', $messageId);

        Queue::assertPushed(StoreChatMessageJob::class, function ($job) {
            return $job->agent->is($this->agent);
        });
    }

    public function test_get_message_by_id(): void
    {
        $this->chatMemory
            ->setAgent($this->agent)
            ->setSessionId($this->sessionId);

        $conversationId = $this->chatMemory->createConversation('Test Conversation');
        $this->chatMemory->setConversationId($conversationId);

        $messageId = $this->chatMemory->addMessage(
            role: 'user',
            message: 'Test message',
            messageId: 'test-msg-123'
        );

        $message = $this->chatMemory->getMessageById($messageId);

        $this->assertIsArray($message);
        $this->assertEquals('test-msg-123', $message['id']);
        $this->assertEquals('user', $message['role']);
        $this->assertEquals('Test message', $message['content']);
    }

    public function test_get_messages_for_conversation(): void
    {
        $this->chatMemory
            ->setAgent($this->agent)
            ->setSessionId($this->sessionId);

        $conversationId = $this->chatMemory->createConversation('Test Conversation');
        $this->chatMemory->setConversationId($conversationId);

        $this->chatMemory->addMessage('user', 'First message');
        $this->chatMemory->addMessage('assistant', 'Second message');

        $messages = $this->chatMemory->getMessagesForConversation();

        $this->assertIsArray($messages);
        $this->assertCount(2, $messages);
    }

    public function test_get_llm_messages(): void
    {
        $this->chatMemory
            ->setAgent($this->agent)
            ->setSessionId($this->sessionId);

        $conversationId = $this->chatMemory->createConversation('Test Conversation');
        $this->chatMemory->setConversationId($conversationId);

        $this->chatMemory->addMessage('user', 'Hello');
        $this->chatMemory->addMessage('assistant', 'Hi there!');

        $llmMessages = $this->chatMemory->getLlmMessages();
        $this->assertIsArray($llmMessages);

        $llmMessagesText = $this->chatMemory->getLlmMessages(asText: true);
        $this->assertIsString($llmMessagesText);
    }

    public function test_update_message(): void
    {
        $this->chatMemory
            ->setAgent($this->agent)
            ->setSessionId($this->sessionId);

        $conversationId = $this->chatMemory->createConversation('Test Conversation');
        $this->chatMemory->setConversationId($conversationId);

        $messageId = $this->chatMemory->addMessage('user', 'Original message');

        $this->chatMemory->updateMessage($messageId, [
            'content' => 'Updated message',
            'custom_field' => 'custom_value',
        ]);

        $message = $this->chatMemory->getMessageById($messageId);
        $this->assertEquals('Updated message', $message['content']);
        $this->assertEquals('custom_value', $message['custom_field']);
    }

    public function test_feedback(): void
    {
        $this->chatMemory
            ->setAgent($this->agent)
            ->setSessionId($this->sessionId);

        $conversationId = $this->chatMemory->createConversation('Test Conversation');
        $this->chatMemory->setConversationId($conversationId);

        $messageId = $this->chatMemory->addMessage('assistant', 'Test response');

        $this->chatMemory->feedback($messageId, satisfied: true);

        $message = $this->chatMemory->getMessageById($messageId);
        $this->assertTrue($message['satisfied']);
    }

    public function test_store_and_get_sources(): void
    {
        $this->chatMemory
            ->setAgent($this->agent)
            ->setSessionId($this->sessionId);

        $conversationId = $this->chatMemory->createConversation('Test Conversation');
        $this->chatMemory->setConversationId($conversationId);

        $messageId = $this->chatMemory->addMessage('assistant', 'Test response');

        $sources = [
            'source1' => ['url' => 'https://example.com/doc1'],
            'source2' => ['url' => 'https://example.com/doc2'],
        ];

        $this->chatMemory->storeSourcesForMessage($messageId, $sources);

        $retrievedSources = $this->chatMemory->getSources();
        $this->assertIsArray($retrievedSources);
        $this->assertArrayHasKey($messageId, $retrievedSources);
        $this->assertEquals($sources, $retrievedSources[$messageId]);
    }

    public function test_get_conversations_for_current_session(): void
    {
        // Use a fresh session ID to avoid interference
        $freshSessionId = 'fresh-session-'.uniqid();

        $this->chatMemory
            ->setAgent($this->agent)
            ->setSessionId($freshSessionId);

        $conversations = $this->chatMemory->getConversationsForCurrentSession();
        $this->assertIsArray($conversations);
        $this->assertCount(0, $conversations);

        $this->chatMemory->createConversation('Test Conversation');
        $conversations = $this->chatMemory->getConversationsForCurrentSession();
        $this->assertCount(1, $conversations);
    }

    public function test_delete_all_conversations(): void
    {
        // Use a fresh session ID to avoid interference
        $freshSessionId = 'fresh-session-'.uniqid();

        $this->chatMemory
            ->setAgent($this->agent)
            ->setSessionId($freshSessionId);

        $this->chatMemory->createConversation('Conversation 1');
        $this->chatMemory->createConversation('Conversation 2');

        $conversations = $this->chatMemory->getConversationsForCurrentSession();
        $this->assertCount(2, $conversations);

        $this->chatMemory->deleteAllConversations();

        $conversations = $this->chatMemory->getConversationsForCurrentSession();
        $this->assertCount(0, $conversations);
    }

    public function test_authentication_methods(): void
    {
        $member = Member::factory()->create();
        Auth::guard('member')->login($member);

        $this->assertTrue($this->chatMemory->isMemberAuth());
        $this->assertInstanceOf(Member::class, $this->chatMemory->member());
        $this->assertTrue($this->chatMemory->member()->is($member));

        Auth::guard('member')->logout();

        $this->assertFalse($this->chatMemory->isMemberAuth());
        $this->assertNull($this->chatMemory->member());
    }

    public function test_legacy_cache_methods_work(): void
    {
        $this->chatMemory
            ->setAgent($this->agent)
            ->setSessionId($this->sessionId);

        // Test cache operations
        $this->chatMemory->cachePut('test_key', 'test_value');
        $this->assertTrue($this->chatMemory->cacheHas('test_key'));
        $this->assertEquals('test_value', $this->chatMemory->cacheGet('test_key'));

        $this->assertTrue($this->chatMemory->cacheForget('test_key'));
        $this->assertFalse($this->chatMemory->cacheHas('test_key'));

        // Test get cache instance
        $cache = $this->chatMemory->getChatCache();
        $this->assertNotNull($cache);
    }

    public function test_remove_message(): void
    {
        $this->chatMemory
            ->setAgent($this->agent)
            ->setSessionId($this->sessionId);

        $conversationId = $this->chatMemory->createConversation('Test Conversation');
        $this->chatMemory->setConversationId($conversationId);

        $messageId1 = $this->chatMemory->addMessage('user', 'Message 1');
        $messageId2 = $this->chatMemory->addMessage('assistant', 'Message 2');

        $messages = $this->chatMemory->getMessagesForConversation();
        $this->assertCount(2, $messages);

        $this->chatMemory->removeMessage($messageId1);

        $messages = $this->chatMemory->getMessagesForConversation();
        $this->assertCount(1, $messages);
        $this->assertEquals($messageId2, $messages[0]['id']);
    }

    public function test_get_last_messages(): void
    {
        $this->chatMemory
            ->setAgent($this->agent)
            ->setSessionId($this->sessionId);

        $conversationId = $this->chatMemory->createConversation('Test Conversation');
        $this->chatMemory->setConversationId($conversationId);

        // Add 5 messages
        for ($i = 1; $i <= 5; $i++) {
            $this->chatMemory->addMessage('user', "Message {$i}");
        }

        $lastMessages = $this->chatMemory->getLastMessages(3);
        $this->assertCount(3, $lastMessages);

        // Should return in reverse order (most recent first)
        $this->assertEquals('Message 5', $lastMessages[0]['content']);
        $this->assertEquals('Message 4', $lastMessages[1]['content']);
        $this->assertEquals('Message 3', $lastMessages[2]['content']);
    }

    public function test_manager_reset_when_configuration_changes(): void
    {
        $this->chatMemory
            ->setAgent($this->agent)
            ->setSessionId($this->sessionId);

        // Initialize managers by creating a conversation
        $this->chatMemory->createConversation('Test');

        // Change agent - should reset managers
        $newAgent = Agent::factory()->create();
        $this->chatMemory->setAgent($newAgent);

        // Should work with new agent
        $this->chatMemory->createConversation('New Test');
        $this->assertTrue(true); // If we get here, managers were reset properly
    }
}
