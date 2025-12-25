# Live Chat System Documentation

This document explains how the live chat (handoff) system works between the ChatVia widget and the admin dashboard.

## Overview

The live chat system allows an admin to "take over" a conversation from the AI and chat directly with the user in real-time. The system uses:

- **WebSocket (Laravel Reverb/Pusher)** for real-time bidirectional communication
- **Server-Sent Events (SSE)** for AI responses (one-way streaming)
- **Presence Channels** for tracking online status

## Architecture

```
┌─────────────┐     WebSocket      ┌─────────────┐     WebSocket      ┌─────────────┐
│   Widget    │◄──────────────────►│   Reverb    │◄──────────────────►│   Admin     │
│   (User)    │                    │   Server    │                    │  Dashboard  │
└─────────────┘                    └─────────────┘                    └─────────────┘
       │                                  │                                  │
       │ SSE (AI responses)               │                                  │
       ▼                                  │                                  │
┌─────────────┐                          │                                  │
│   Laravel   │◄─────────────────────────┴──────────────────────────────────┘
│   Backend   │         HTTP API
└─────────────┘
```

## Widget Flow

### 1. Session Initialization

When the widget loads:

```javascript
// widget.js - _initializeSession()
this.sessionId = sessionId;
handoff.connectPusher(this);  // Establish WebSocket immediately
handoff.startHeartbeat(this); // Start heartbeat for online status
```

### 2. WebSocket Connection

The widget establishes a persistent WebSocket connection on initialization:

```javascript
// handoff.js - connectPusher()
widget.pusherClient = new Pusher(appKey, {
  wsHost: wsHost,
  wsPort: wsPort,
  forceTLS: forceTLS,
  authEndpoint: `${apiUrl}/broadcasting/auth`,
  auth: {
    headers: {
      'x-session-id': widget.sessionId,
      'X-AGENT-ID': widget.agentId
    }
  }
});
```

### 3. Channel Subscription

When a conversation/thread is loaded, the widget subscribes to its presence channel:

```javascript
// handoff.js - subscribeToChannel()
const channelName = `presence-chat.${conversationId}`;
widget.pusherChannel = widget.pusherClient.subscribe(channelName);

// Listen for events
widget.pusherChannel.bind('message', handleWebSocketMessage);
widget.pusherChannel.bind('typing', handleTypingEvent);
widget.pusherChannel.bind('handoff', handleHandoffEvent);
```

### 4. Handoff Modes

The widget operates in three modes:

```javascript
export const HANDOFF_MODE = {
  AI: 'ai',       // Default - SSE for AI responses
  PENDING: 'pending', // Waiting for agent (unused - admin initiates)
  HUMAN: 'human'  // Live chat with human agent via WebSocket
};
```

### 5. Handling Admin Takeover

When an admin takes over, a `handoff` event is broadcast:

```javascript
// Widget receives:
widget.pusherChannel.bind('handoff', (data) => {
  // data = { status: 'human', admin_name: 'John', admin_id: '1' }
  handleHandoffEvent(widget, data);
});

// switchToHumanMode() is called:
// - Changes widget.handoffMode to HUMAN
// - Shows notification to user
// - Updates UI (header color, end chat button)
// - Disables menu options
```

### 6. Message Flow in Human Mode

**User sends message:**
```javascript
// Uses SSE endpoint but backend detects handoff mode
POST /widget/stream
// Backend calls HandoffService::sendUserMessage()
// - Saves to memory + database
// - Broadcasts via ChatMessageEvent
// Returns: { event: 'handoff_ack' }
```

**Admin sends message:**
```javascript
// Admin dashboard calls:
POST /my/agents/{agent}/handoff/{conversation}/message
// Backend calls HandoffService::sendAdminMessage()
// - Saves to memory + database  
// - Broadcasts via ChatMessageEvent

// Widget receives via WebSocket:
widget.pusherChannel.bind('message', (data) => {
  // data = { content, sender_name, role: 'agent', id }
  handleWebSocketMessage(widget, data);
});
```

### 7. Typing Indicators

Uses Pusher client events (whisper) for efficiency:

```javascript
// Widget sends (user typing):
widget.pusherChannel.trigger('client-typing', {
  is_typing: true,
  typer_type: 'user',
  typer_name: 'Visitor'
});

// Admin listens:
channel.listenForWhisper('typing', (data) => {
  // Show typing indicator
});
```

### 8. Ending the Chat

User can end the live chat:

```javascript
// Widget calls:
POST /widget/handoff/end
// Backend updates conversation status
// Broadcasts handoff event with status: 'returned'

// Widget switches back to AI mode
switchToAIMode(widget, "You're back with our AI assistant.");
```

## Admin Dashboard Flow

### 1. Chat Log Component Structure

```
ChatLog/
├── index.vue           # Main container with tabs
├── ConversationsSidebar.vue  # List of conversations
├── HandoffPanel.vue    # Live chat interface
└── useChatLog.ts       # State management composable
```

### 2. Handoff Composable

```typescript
// composables/useHandoff.ts
export function useHandoff(agentId, conversationId) {
  // State
  const handoffState = ref({ status: 'ai', adminId: null, adminName: null });
  const presenceMembers = ref([]);  // Users in channel
  const typingState = ref(null);
  
  // WebSocket via Laravel Echo
  function joinChannel(convId) {
    currentChannel = echo.join(`chat.${convId}`)
      .here((members) => { presenceMembers.value = members; })
      .listen('.message', handleMessage)
      .listen('.handoff', handleHandoff)
      .listenForWhisper('typing', handleTyping);
  }
  
  // API calls
  async function takeOver() { /* POST to takeover endpoint */ }
  async function returnToAi() { /* POST to return endpoint */ }
  async function sendMessage(content) { /* POST message */ }
}
```

### 3. Taking Over a Conversation

```typescript
// HandoffPanel.vue
async function takeOver() {
  await handoff.takeOver();
  // This calls: POST /my/agents/{agent}/handoff/{conversation}/takeover
  // Backend broadcasts ChatHandoffEvent with status: 'human'
  // Widget receives and switches to human mode
}
```

### 4. Real-time Updates

Messages appear instantly via WebSocket:

```typescript
// useChatLog.ts
watch(() => handoff.newMessageReceived.value, (newMsg) => {
  appendMessage(newMsg);  // Add to local state
  scrollToBottom();
});
```

## Backend Services

### HandoffService

Located at `app/Services/Chat/HandoffService.php`:

```php
class HandoffService
{
    // Admin takes over conversation
    public function takeOver(ChatConversation $conversation, User $admin): bool
    {
        $conversation->update([
            'handoff_status' => 'human',
            'handoff_user_id' => $admin->id,
            'handoff_at' => now(),
        ]);
        
        ChatHandoffEvent::dispatch($conversation->id, 'human', $admin->id, $admin->name);
        return true;
    }
    
    // Admin sends message
    public function sendAdminMessage(ChatConversation $conversation, User $admin, string $content): ?ChatMessage
    {
        // Save to memory cache
        $chatMemory->addMessage(role: 'agent', message: $content);
        
        // Save to database
        ChatMessage::create([...]);
        
        // Broadcast to widget
        ChatMessageEvent::dispatch(...);
    }
    
    // User sends message during handoff
    public function sendUserMessage(ChatConversation $conversation, string $content, ...): ?string
    {
        // Create payload and dispatch job
        $chatMemory->addMessageViaPayload($payload);
        
        // Broadcast to admin
        ChatMessageEvent::dispatch(...);
    }
}
```

### Broadcasting Events

All events are broadcast on the presence channel `chat.{conversationId}`:

- **ChatHandoffEvent** - Handoff status changes
- **ChatMessageEvent** - New messages
- **ChatTypingEvent** - Typing indicators (server-side fallback)

## Configuration

### Widget Build Variables

Set in `rollup.config.js` or environment:

```javascript
__REVERB_APP_KEY__   // Reverb/Pusher app key
__REVERB_HOST__      // WebSocket host (e.g., 'chatvia.test')
__REVERB_PORT__      // WebSocket port (e.g., 443)
__REVERB_SCHEME__    // 'https' or 'http'
```

### Laravel Broadcasting

Configure in `config/broadcasting.php` and `config/reverb.php`:

```php
// broadcasting.php
'reverb' => [
    'driver' => 'reverb',
    'key' => env('REVERB_APP_KEY'),
    'secret' => env('REVERB_APP_SECRET'),
    'app_id' => env('REVERB_APP_ID'),
    // ...
],

// reverb.php
'apps' => [
    [
        'app_id' => env('REVERB_APP_ID'),
        'key' => env('REVERB_APP_KEY'),
        'secret' => env('REVERB_APP_SECRET'),
        'options' => [
            'host' => env('REVERB_HOST', 'localhost'),
            'port' => env('REVERB_PORT', 443),
            'scheme' => env('REVERB_SCHEME', 'https'),
        ],
    ],
],
```

## User Online Status

### Heartbeat Mechanism

Widget sends periodic heartbeats to update `last_activity_at`:

```javascript
// handoff.js - startHeartbeat()
widget._heartbeatInterval = setInterval(() => {
  fetch(`${__API_URL__}/heartbeat`, {
    method: 'POST',
    headers: { 'x-session-id': widget.sessionId, ... },
    body: JSON.stringify({ conversation_id: conversationId })
  });
}, 30000); // Every 30 seconds
```

### Online Filter

Admin can filter conversations by online status:

```php
// ChatConversationQueryBuilder.php
public function filterByOnlineStatus(?bool $onlineOnly): self
{
    return $this->when($onlineOnly, function ($query) {
        $query->where('last_activity_at', '>=', now()->subMinutes(3));
    });
}
```

## Error Handling

- If WebSocket is not configured, live chat is simply not available
- No polling fallback - WebSocket is the only real-time mechanism
- SSE continues to work for AI responses regardless of WebSocket status

## Key Files

### Widget
- `src/state/handoff.js` - All handoff logic
- `src/state/threads.js` - Thread management
- `src/widget.js` - Main widget class

### ChatVia
- `app/Services/Chat/HandoffService.php` - Handoff business logic
- `app/Services/Chat/ChatStreamService.php` - SSE streaming
- `app/Events/Chat/ChatHandoffEvent.php` - Handoff broadcasts
- `app/Events/Chat/ChatMessageEvent.php` - Message broadcasts
- `resources/js/composables/useHandoff.ts` - Admin WebSocket composable
- `resources/js/pages/agents/ChatLog/HandoffPanel.vue` - Admin chat UI

