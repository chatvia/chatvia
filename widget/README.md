<p align="center">
  <img src="logo.png" alt="ChatVia Logo" width="120" height="120">
</p>

A customizable, embeddable chat widget with Markdown support, designed to connect via Server-Sent Events (SSE).

## Features

*   **Embeddable:** Easily integrate the chat widget into any web page.
*   **Two launchers:** a chat bubble in the corner, or a floating "ask anything" bar.
*   **Markdown Support:** Renders messages formatted with Markdown.
*   **Streaming Responses:** Shows the agent's reply as it streams from the server (Server-Sent Events).

## Getting Started

These instructions will get you a copy of the project up and running on your local machine for development and testing purposes.

### Prerequisites

*   [Node.js](https://nodejs.org/) (LTS version recommended)
*   [npm](https://www.npmjs.com/) (comes with Node.js)

### Installation

```bash
npm install
npm run dev
```

### Configuration

The project uses environment variables for configuration, managed via `.env` files.

1.  **Create a `.env` file:**
    ```bash
    touch .env
    ```
2.  **Edit `.env`:** Add the following required variables:
    ```dotenv
    # Required: Application version for cache-busting
    VITE_APP_VERSION=1.0.0
    
    # Required: Backend API URL
    VITE_API_URL=https://chatvia.test/widget
    
    # Required: CDN/Script URL where the widget files are hosted
    VITE_SCRIPT_URL=https://chatvia.test
    
    # Optional: Reverb configuration for the live handoff feature.
    # Without a key and host, live chat is turned off.
    VITE_REVERB_APP_KEY=your-reverb-app-key
    VITE_REVERB_HOST=ws.example.com
    VITE_REVERB_PORT=443
    VITE_REVERB_SCHEME=https
    
    # Build environment
    NODE_ENV=production
    
    # API endpoints will be appended to VITE_API_URL:
    # - Sessions: https://chatvia.test/widget/sessions/init
    # - Conversations: https://chatvia.test/widget/conversations
    # - Agent verification: https://chatvia.test/widget/agents/verify
    # - SSE Stream: https://chatvia.test/widget/stream
    ```

⚠️ **Important:** Make sure to set `VITE_APP_VERSION` before building. This version determines the directory structure in `dist/` for cache-busting.

### Point a built widget at your own server

The values above are only defaults. You can use one build with any server.
Add these optional attributes to the embed script tag:

| Attribute | Example | Default |
|---|---|---|
| `data-api-url` | `https://chat.example.com/widget` | `VITE_API_URL` |
| `data-reverb-host` | `ws.example.com` | `VITE_REVERB_HOST` |
| `data-reverb-port` | `443` | `VITE_REVERB_PORT` |
| `data-reverb-key` | `your-reverb-app-key` | `VITE_REVERB_APP_KEY` |
| `data-reverb-scheme` | `https` or `http` | `VITE_REVERB_SCHEME` |

```html
<script id="chatbot-embed-script"
    src="https://chat.example.com/widget-assets/embed.js"
    data-agent-id="YOUR_AGENT_ID"
    data-api-url="https://chat.example.com/widget"
    data-reverb-host="ws.example.com"
    data-reverb-key="your-reverb-app-key"
    defer></script>
```

If `VITE_SCRIPT_URL` is not set, the embed script loads the widget bundle from
the same folder as `embed.js`.

## Running Locally

The widget talks to a running Chatvia server. Set `VITE_API_URL` in `.env` to
that server's widget API (for example `http://localhost:8000/widget`), then
start the development server:

```bash
npm run dev
```

Vite serves the widget on `http://localhost:3000` and opens `index.html`.
`bar.html` shows the bar launcher.

## Building for Production

⚠️ **Before building, ensure you have configured the `.env` file with `VITE_APP_VERSION`!**

To create optimized static assets for deployment:

```bash
# Set the version in .env first
# VITE_APP_VERSION=1.0.5

# Build for production
npm run build:prod
```

This will generate the following structure:

```
dist/
  ├── embed.js               # Small loader script (sites embed this)
  ├── version.json           # Current version, read by embed.js
  └── {VERSION}/             # Version folder (e.g., 1.0.5/)
      └── widget.min.js      # The widget bundle (bubble and bar modes)
```

Production builds also write `.gz` and `.br` copies next to each file.

### Versioning Strategy
See [VERSIONING.md](./VERSIONING.md) for detailed information about the three-layer versioning architecture.

## Embed Options

All options are `data-` attributes on the embed script tag.

- `data-agent-id` (required): the agent's public ID.
- `data-mode`: `bubble` (default, a chat bubble in the corner) or `bar` (a floating "ask anything" bar).
- `data-language`: a language code, or `auto` (default) to follow the visitor's browser. Supported codes: `ar`, `da`, `de`, `en`, `es`, `fa`, `fi`, `fr`, `hi`, `id`, `ja`, `ko`, `no`, `pl`, `pt`, `ru`, `sv`, `tr`, `vi`, `zh`. Other codes fall back to English.
- `data-theme`: `auto`, `light` or `dark`. Without it, the widget uses the agent's setting.
- `data-auth`: a signed token, needed only for protected (non-public) agents.
- `data-mount-id`: the ID of an element to render the widget into, instead of the page corner.

`data-type="widget"` and `data-type="search"` still work for older embeds (they mean `bubble` and `bar`), but they are deprecated.

```html
<script id="chatbot-embed-script"
    src="https://your-cdn.com/embed.js"
    data-agent-id="YOUR_AGENT_ID"
    data-mode="bar"
    data-language="auto"
    defer></script>
```

## JavaScript API

The widget exposes a JavaScript API that allows you to control the chat programmatically.

### Basic Usage

```html
<script id="chatbot-embed-script" src="https://your-cdn.com/embed.js" data-agent-id="YOUR_AGENT_ID" defer></script>

<script>
  // Wait for widget to be ready
  chatvia.on('ready', () => {
    console.log('Widget is ready!');
  });

  // Open the chat
  chatvia.open();

  // Send a message programmatically
  chatvia.send('Hello, I need help!');
</script>
```

### API Methods

| Method | Description | Example |
|--------|-------------|---------|
| `chatvia.open()` | Open the chat window | `chatvia.open()` |
| `chatvia.close()` | Close the chat window | `chatvia.close()` |
| `chatvia.toggle()` | Toggle chat open/closed. Returns new state | `const isOpen = chatvia.toggle()` |
| `chatvia.isOpen()` | Check if chat is open | `if (chatvia.isOpen()) { ... }` |
| `chatvia.send(message)` | Send a message to the chat | `chatvia.send('Hello!')` |
| `chatvia.newChat()` | Start a new conversation | `chatvia.newChat()` |
| `chatvia.destroy()` | Remove widget and clean up resources | `chatvia.destroy()` |

### Events

Subscribe to widget events using `chatvia.on(event, callback)`:

```javascript
// When chat window opens
chatvia.on('open', () => {
  console.log('Chat opened');
});

// When chat window closes
chatvia.on('close', () => {
  console.log('Chat closed');
});

// When widget is ready
chatvia.on('ready', () => {
  console.log('Widget initialized');
});

// Unsubscribe from events
const unsubscribe = chatvia.on('open', myCallback);
unsubscribe(); // Stop listening

// Or use chatvia.off()
chatvia.off('open', myCallback); // Remove specific callback
chatvia.off('open'); // Remove all callbacks for this event
```

### Legacy Function-Style API

For backward compatibility, you can also use the function-style API:

```javascript
chatvia('open');
chatvia('close');
chatvia('toggle');
chatvia('send', 'Hello!');
chatvia('sendMessage', 'Hello!'); // Alias for send
chatvia('newChat');
chatvia('destroy');
```

### Example: Custom Open Button

```html
<button onclick="chatvia.open()">Chat with us</button>
<button onclick="chatvia.send('I want to talk to sales')">Talk to Sales</button>
```

### Example: Open Chat After Delay

```javascript
// Open chat after 5 seconds
setTimeout(() => {
  chatvia.open();
}, 5000);
```

### Example: Send Message Based on Page

```javascript
// Send different messages based on the page
chatvia.on('ready', () => {
  const page = window.location.pathname;
  
  if (page.includes('/pricing')) {
    chatvia.open();
    chatvia.send('I have a question about pricing');
  }
});
```

## Deployment

CI (`.github/workflows/widget.yml` at the repository root) lints, tests and
builds the widget on every change under `widget/`. It does not publish it.

To deploy, build with your own values in `.env` (see **Configuration**) and
upload the contents of `dist/` to any static host or CDN. Then point the embed
script tag at the uploaded `embed.js`.

## License

The widget is released under the MIT license.

