<p align="center">
  <img src="logo.png" alt="ChatVia Logo" width="120" height="120">
</p>

A customizable, embeddable chat widget with Markdown support, designed to connect via Server-Sent Events (SSE).

## Features

*   **Embeddable:** Easily integrate the chat widget into any web page.
*   **Customizable:** Adapt the look and feel (though details on customization need implementation/documentation).
*   **Markdown Support:** Renders messages formatted with Markdown.
*   **SSE Communication:** Connects to an SSE backend for real-time messaging.
*   **Streaming Responses:** Simulates streaming responses from the backend.
*   **Search Widget:** New standalone search component with side-panel results.

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
    VITE_API_URL=https://chatvia.test/api/widget
    
    # Required: CDN/Script URL where the widget files are hosted
    VITE_SCRIPT_URL=https://chatvia.test
    
    # Optional: Reverb/Pusher configuration for live handoff feature
    VITE_REVERB_APP_KEY=laravel-herd
    VITE_REVERB_HOST=reverb.herd.test
    VITE_REVERB_PORT=443
    VITE_REVERB_SCHEME=https
    
    # Build environment
    NODE_ENV=production
    
    # API endpoints will be appended to VITE_API_URL:
    # - Sessions: https://chatvia.test/api/widget/sessions
    # - Conversations: https://chatvia.test/api/widget/conversations
    # - Agent verification: https://chatvia.test/api/widget/agents/verify
    # - SSE Stream: https://chatvia.test/api/widget/stream
    ```

⚠️ **Important:** Make sure to set `VITE_APP_VERSION` before building. This version determines the directory structure in `dist/` for cache-busting.

## Running Locally

To run the project locally, you'll typically need to run both the frontend development server and the backend simulation server.

1.  **Start the Backend Simulation Server:**
    This server simulates responses for the chat widget.
    ```bash
    node server.js
    ```
    This will start the SSE server, usually on port 8080 (or the port specified by the `PORT` environment variable).

2.  **Start the Frontend Development Server:**
    This command uses Vite to serve the frontend with hot module replacement.
    ```bash
    npm run dev
    ```
    Vite will output the URL where you can access the development version (likely `http://localhost:5173` or similar, check the console output). Open `dev.html` or `index.html` in your browser through this URL.

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
  ├── embed.js               # Minimal embed script (users embed this)
  ├── version.json           # Current version info (cache-busted)
  └── {VERSION}/             # Version-specific directory (e.g., 1.0.5/)
      ├── bootstrap.js       # Versioned bootstrap script
      ├── widget.min.js      # Main chat widget bundle
      ├── search.min.js      # Search widget bundle
      └── version.json       # Version info (for this specific version)
```

### Build Output Files:
- `dist/embed.js` - Minimal embed script (what users embed on their sites)
- `dist/{VERSION}/bootstrap.js` - Versioned bootstrap script for loading either widget or search
- `dist/{VERSION}/widget.min.js` - Main chat widget
- `dist/{VERSION}/search.min.js` - Search widget
- `dist/version.json` - Version information for cache-busting

### Versioning Strategy
See [VERSIONING.md](./VERSIONING.md) for detailed information about the three-layer versioning architecture.

## Search Widget

The search widget provides AI-powered search with a side-panel interface. It uses the same embed script as the chat widget but with `type="search"`.

### Features

- **Flexible Positioning:** Can be positioned at the top or bottom of the page
- **Side Panel Interface:** Opens a beautiful side panel on the right for results
- **Real-time Streaming:** Streams AI responses using SSE
- **Markdown Support:** Renders formatted responses
- **Responsive Design:** Works on all devices
- **Same Infrastructure:** Uses the same embed.js as the chat widget

### Usage

Use the embed script with `type="search"`:

```html
<!-- For Chat Widget (default) -->
<script src="https://your-cdn.com/embed.js" agent-id="YOUR_AGENT_ID"></script>

<!-- For Search Widget -->
<script 
  src="https://your-cdn.com/embed.js" 
  agent-id="YOUR_AGENT_ID"
  type="search"
  data-search-position="top"
  data-search-placeholder="Ask a question..."
></script>

<!-- Chat Widget in Arabic -->
<script 
  src="https://your-cdn.com/embed.js" 
  agent-id="YOUR_AGENT_ID"
  language="ar"
></script>

<!-- Search Widget with Auto-detect Language -->
<script 
  src="https://your-cdn.com/embed.js" 
  agent-id="YOUR_AGENT_ID"
  type="search"
  language="auto"
></script>
```

#### Configuration Options

- `type`: `'widget'` (default) or `'search'` - Which component to load
- `language`: `'en'`, `'ar'`, or `'auto'` (default) - Language for the widget interface
- `data-search-position`: `'top'` or `'bottom'` - Where to display the search bar (default: `'top'`)
- `data-search-placeholder`: String - Placeholder text for the search input (default: `'Ask a question...'`)

### Demo

Open `search.html` in your browser to see the search widget in action.

## JavaScript API

The widget exposes a JavaScript API that allows you to control the chat programmatically.

### Basic Usage

```html
<script src="https://your-cdn.com/embed.js" agent-id="YOUR_AGENT_ID"></script>

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

## Deployment CI/CD

```bash
# Tag the release with v prefix
git tag v2.0
git push origin --tags
```

