# Widget Handoff Feature - Implementation Guide

## Overview

This feature allows widget users to request a human agent when they need assistance beyond what the AI can provide.

### Flow Summary
1. User requests human assistance
2. User waits in a pending state
3. Admin accepts the request from dashboard
4. Live chat begins via WebSocket
5. Admin returns user to AI when done

---

## Required Headers

All widget API endpoints require these headers:

```
x-session-id: {session_id}
X-AGENT-ID: {agent_id}
```

---

## API Endpoints

### 1. Request Human Assistance

**When to call:** User clicks "Talk to Human" button

```http
POST /widget/handoff/request
Content-Type: application/json

Headers:
  x-session-id: {session_id}
  X-AGENT-ID: {agent_id}

Body:
{
  "conversation_id": "01HXYZ..."
}
```

**Response (Success):**
```json
{
  "status": "pending",
  "conversation_id": "01HXYZ...",
  "message": "Your request has been sent. Please wait for an agent to connect."
}
```

**Response (Already Connected):**
```json
{
  "status": "human",
  "message": "Already connected to a human agent."
}
```

---

### 2. Check Handoff Status (Polling Alternative)

**When to call:** Every 5 seconds while in pending state (if not using WebSocket)

```http
GET /widget/handoff/status

Headers:
  x-session-id: {session_id}
  x-thread-id: {conversation_id}
```

**Response:**
```json
{
  "conversation_id": "01HXYZ...",
  "handoff_status": "pending | human | ai",
  "is_human_controlled": false,
  "admin_name": null
}
```

---

### 3. WebSocket Authentication

**When to call:** Before subscribing to presence channel

```http
POST /widget/broadcasting/auth
Content-Type: application/json

Headers:
  x-session-id: {session_id}
  X-AGENT-ID: {agent_id}

Body:
{
  "channel_name": "presence-chat.{conversation_id}",
  "socket_id": "{pusher_socket_id}"
}
```

**Response:** Pusher auth object (pass directly to Pusher client)

---

### 4. Send Typing Indicator

**When to call:** User starts/stops typing during live chat mode

```http
POST /widget/typing
Content-Type: application/json

Headers:
  x-session-id: {session_id}
  X-AGENT-ID: {agent_id}

Body:
{
  "conversation_id": "01HXYZ...",
  "is_typing": true
}
```

---

### 5. Send Message (Existing Endpoint)

During handoff, continue using the existing stream endpoint. Messages will be delivered to the admin via WebSocket.

```http
POST /widget/stream
Content-Type: application/json

Headers:
  x-session-id: {session_id}
  x-thread-id: {conversation_id}
  X-AGENT-ID: {agent_id}

Body:
{
  "content": "User message here"
}
```

---

## WebSocket Setup

### Connection Configuration

```javascript
import Pusher from 'pusher-js';

const pusher = new Pusher(REVERB_APP_KEY, {
  wsHost: REVERB_HOST,
  wsPort: REVERB_PORT,
  wssPort: REVERB_PORT,
  forceTLS: true,
  enabledTransports: ['ws', 'wss'],
  authEndpoint: `${API_BASE_URL}/widget/broadcasting/auth`,
  auth: {
    headers: {
      'x-session-id': sessionId,
      'X-AGENT-ID': agentId,
    }
  }
});
```

### Channel Subscription

```javascript
const channel = pusher.subscribe(`presence-chat.${conversationId}`);
```

---

## WebSocket Events

### 1. `handoff` - Handoff Status Changed

```javascript
channel.bind('handoff', (data) => {
  console.log('Handoff event:', data);
  
  // data structure:
  // {
  //   conversation_id: "01HXYZ...",
  //   status: "pending" | "human" | "returned" | "ai",
  //   admin_id: "123" | null,
  //   admin_name: "Support Agent" | null,
  //   timestamp: "2025-12-02T20:00:00Z"
  // }
  
  switch (data.status) {
    case 'human':
      // Agent connected - show "Connected to {admin_name}"
      enableLiveChatMode(data.admin_name);
      break;
    case 'returned':
    case 'ai':
      // Back to AI - show "You're back with our AI assistant"
      switchToAIMode();
      break;
  }
});
```

### 2. `message` - New Message from Admin

```javascript
channel.bind('message', (data) => {
  console.log('New message:', data);
  
  // data structure:
  // {
  //   id: "01HXYZ...",
  //   conversation_id: "01HXYZ...",
  //   content: "Hello, how can I help?",
  //   role: "admin" | "user" | "assistant",
  //   sender_name: "Support Agent",
  //   sender_id: "123",
  //   timestamp: "2025-12-02T20:00:00Z"
  // }
  
  if (data.role === 'admin') {
    appendMessage({
      content: data.content,
      role: 'assistant', // Display as assistant bubble
      senderName: data.sender_name,
      isHuman: true,
      timestamp: data.timestamp,
    });
  }
});
```

### 3. `typing` - Admin Typing Indicator

```javascript
channel.bind('typing', (data) => {
  console.log('Typing event:', data);
  
  // data structure:
  // {
  //   conversation_id: "01HXYZ...",
  //   is_typing: true | false,
  //   typer_type: "admin" | "user",
  //   typer_name: "Support Agent",
  //   typer_id: "123",
  //   timestamp: "2025-12-02T20:00:00Z"
  // }
  
  if (data.typer_type === 'admin') {
    if (data.is_typing) {
      showTypingIndicator(data.typer_name);
    } else {
      hideTypingIndicator();
    }
  }
});
```

---

## Widget State Machine

```
┌─────────────────┐
│    AI MODE      │  ← Default state
│                 │    Uses SSE for AI responses
│                 │    Shows "Talk to Human" button
└────────┬────────┘
         │
         │ User clicks "Talk to Human"
         │ 
         │ Actions (in order):
         │   1. POST /widget/handoff/request
         │   2. If response.status === "pending":
         │      ⭐ SUBSCRIBE NOW to presence-chat.{conversationId}
         │   3. Show "Waiting for agent..." UI
         ▼
┌─────────────────┐
│    PENDING      │  ← Waiting for agent
│                 │    ⭐ ALREADY SUBSCRIBED to WebSocket
│                 │    Show spinner/waiting message
│                 │    Listening for 'handoff' event...
│                 │    User can still type (messages queued)
└────────┬────────┘
         │
         │ ⭐ Receive WebSocket 'handoff' event with status: 'human'
         │   (This is how widget knows admin accepted!)
         ▼
┌─────────────────┐
│   HUMAN MODE    │  ← Live chat with admin
│                 │    Show "Connected to {admin_name}"
│                 │    Display admin typing indicator
│                 │    Receive messages via WebSocket
│                 │    Send messages via /widget/stream
│                 │    Send typing via /widget/typing
└────────┬────────┘
         │
         │ Receive 'handoff' event with status: 'returned'
         ▼
┌─────────────────┐
│    AI MODE      │  ← Back to AI
│                 │    Show "You're back with AI" message
│                 │    Optionally unsubscribe from WebSocket
│                 │    Resume SSE for AI responses
└─────────────────┘
```

### ⚠️ Important: When to Subscribe

Subscribe to WebSocket **immediately after** `/widget/handoff/request` returns `pending`:

```javascript
// ✅ CORRECT - Subscribe right after request
const response = await fetch('/widget/handoff/request', { ... });
const data = await response.json();

if (data.status === 'pending') {
  // Subscribe NOW, don't wait for human status
  subscribeToWebSocket(conversationId);
  showWaitingUI();
}

// ❌ WRONG - Don't wait for human status to subscribe
// The widget learns about human status FROM the WebSocket event!
```

---

## Complete Implementation Example

```javascript
class WidgetHandoff {
  constructor(config) {
    this.apiBaseUrl = config.apiBaseUrl;
    this.sessionId = config.sessionId;
    this.agentId = config.agentId;
    this.conversationId = config.conversationId;
    
    this.pusher = null;
    this.channel = null;
    this.state = 'ai'; // 'ai' | 'pending' | 'human'
    this.adminName = null;
    
    // Callbacks
    this.onStateChange = config.onStateChange || (() => {});
    this.onMessage = config.onMessage || (() => {});
    this.onTyping = config.onTyping || (() => {});
  }
  
  // Get common headers
  getHeaders() {
    return {
      'Content-Type': 'application/json',
      'x-session-id': this.sessionId,
      'X-AGENT-ID': this.agentId,
    };
  }
  
  // Request human assistance
  async requestHuman() {
    if (this.state !== 'ai') return;
    
    try {
      const response = await fetch(`${this.apiBaseUrl}/widget/handoff/request`, {
        method: 'POST',
        headers: this.getHeaders(),
        body: JSON.stringify({
          conversation_id: this.conversationId,
        }),
      });
      
      const data = await response.json();
      
      if (data.status === 'pending' || data.status === 'human') {
        this.setState(data.status);
        this.connectWebSocket();
      }
      
      return data;
    } catch (error) {
      console.error('Failed to request human:', error);
      throw error;
    }
  }
  
  // Connect to WebSocket
  connectWebSocket() {
    if (this.pusher) return;
    
    this.pusher = new Pusher(REVERB_APP_KEY, {
      wsHost: REVERB_HOST,
      wsPort: REVERB_PORT,
      wssPort: REVERB_PORT,
      forceTLS: true,
      enabledTransports: ['ws', 'wss'],
      authEndpoint: `${this.apiBaseUrl}/widget/broadcasting/auth`,
      auth: {
        headers: {
          'x-session-id': this.sessionId,
          'X-AGENT-ID': this.agentId,
        },
      },
    });
    
    this.channel = this.pusher.subscribe(`presence-chat.${this.conversationId}`);
    
    // Listen for handoff status changes
    this.channel.bind('handoff', (data) => {
      this.handleHandoffEvent(data);
    });
    
    // Listen for messages from admin
    this.channel.bind('message', (data) => {
      if (data.role === 'admin') {
        this.onMessage(data);
      }
    });
    
    // Listen for typing indicators
    this.channel.bind('typing', (data) => {
      if (data.typer_type === 'admin') {
        this.onTyping(data);
      }
    });
  }
  
  // Handle handoff event
  handleHandoffEvent(data) {
    switch (data.status) {
      case 'human':
        this.adminName = data.admin_name;
        this.setState('human');
        break;
      case 'returned':
      case 'ai':
        this.adminName = null;
        this.setState('ai');
        break;
    }
  }
  
  // Set state and notify
  setState(newState) {
    const oldState = this.state;
    this.state = newState;
    this.onStateChange(newState, oldState, this.adminName);
  }
  
  // Send typing indicator (call on input change)
  async sendTyping(isTyping) {
    if (this.state !== 'human') return;
    
    try {
      await fetch(`${this.apiBaseUrl}/widget/typing`, {
        method: 'POST',
        headers: this.getHeaders(),
        body: JSON.stringify({
          conversation_id: this.conversationId,
          is_typing: isTyping,
        }),
      });
    } catch (error) {
      // Silently fail for typing indicators
      console.warn('Failed to send typing:', error);
    }
  }
  
  // Check if in human mode
  isHumanMode() {
    return this.state === 'human';
  }
  
  // Check if pending
  isPending() {
    return this.state === 'pending';
  }
  
  // Disconnect WebSocket
  disconnect() {
    if (this.channel) {
      this.pusher.unsubscribe(`presence-chat.${this.conversationId}`);
      this.channel = null;
    }
    if (this.pusher) {
      this.pusher.disconnect();
      this.pusher = null;
    }
  }
}

// Usage Example
const handoff = new WidgetHandoff({
  apiBaseUrl: 'https://api.example.com',
  sessionId: 'session-123',
  agentId: 'agent-456',
  conversationId: 'conv-789',
  
  onStateChange: (newState, oldState, adminName) => {
    console.log(`State changed: ${oldState} → ${newState}`);
    
    switch (newState) {
      case 'pending':
        showWaitingUI();
        break;
      case 'human':
        showConnectedUI(adminName);
        break;
      case 'ai':
        showAIUI();
        break;
    }
  },
  
  onMessage: (data) => {
    appendMessageToChat({
      content: data.content,
      isFromAdmin: true,
      senderName: data.sender_name,
      timestamp: data.timestamp,
    });
  },
  
  onTyping: (data) => {
    if (data.is_typing) {
      showTypingIndicator(data.typer_name);
    } else {
      hideTypingIndicator();
    }
  },
});

// When user clicks "Talk to Human"
document.getElementById('talk-to-human-btn').addEventListener('click', async () => {
  await handoff.requestHuman();
});

// When user types (debounced)
let typingTimeout;
document.getElementById('message-input').addEventListener('input', () => {
  handoff.sendTyping(true);
  
  clearTimeout(typingTimeout);
  typingTimeout = setTimeout(() => {
    handoff.sendTyping(false);
  }, 1000);
});

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
  handoff.disconnect();
});
```

---

## UI Recommendations

### 1. "Talk to Human" Button
- Show only when `state === 'ai'`
- Position: Bottom of chat or in header menu
- Style: Secondary button, not too prominent

### 2. Pending State UI
- Show spinner with message: "Connecting you to a human agent..."
- Allow user to cancel: "Cancel" button that calls `/widget/handoff/request` with cancel action
- Optionally show estimated wait time

### 3. Connected State UI
- Show banner: "You're now chatting with {admin_name}"
- Different message bubble style for admin messages (optional)
- Show typing indicator when admin is typing

### 4. Return to AI UI
- Show transition message: "You're back with our AI assistant"
- Auto-dismiss after 3 seconds
- Resume normal AI chat mode

---

## Error Handling

```javascript
// Handle connection errors
pusher.connection.bind('error', (error) => {
  console.error('WebSocket error:', error);
  // Fall back to polling
  startPollingStatus();
});

// Handle disconnection
pusher.connection.bind('disconnected', () => {
  console.log('WebSocket disconnected');
  // Attempt reconnection or fall back to polling
});

// Polling fallback
async function startPollingStatus() {
  const poll = async () => {
    try {
      const response = await fetch(
        `${apiBaseUrl}/widget/handoff/status`,
        {
          headers: {
            'x-session-id': sessionId,
            'x-thread-id': conversationId,
          },
        }
      );
      const data = await response.json();
      
      if (data.handoff_status === 'human') {
        // Retry WebSocket connection
        handoff.connectWebSocket();
      }
    } catch (error) {
      console.error('Polling error:', error);
    }
  };
  
  // Poll every 5 seconds
  setInterval(poll, 5000);
}
```

---

## Environment Variables

```env
REVERB_APP_KEY=your-reverb-app-key
REVERB_HOST=your-reverb-host.com
REVERB_PORT=443
```

---

## Summary

| State | User Can | Widget Shows | Communication |
|-------|----------|--------------|---------------|
| AI | Chat with AI, Request human | Normal chat + "Talk to Human" button | SSE |
| Pending | Wait, Cancel | "Waiting for agent..." spinner | WebSocket (listening) |
| Human | Chat with admin | "Connected to {name}" banner | WebSocket (bidirectional) |

