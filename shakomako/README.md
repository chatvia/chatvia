<p align="center">
  <img src="logo.png" alt="Shakomako logo" width="120" height="120">
</p>

# Shakomako

Shakomako is the embeddable chat widget of [Chatvia](https://github.com/chatvia/chatvia):
a chat bubble or a command bar that a website adds with one `<script>` tag. It
streams the agent's answers from a Chatvia server with Server-Sent Events
(SSE). The name is Iraqi slang, roughly "what's up?".

It lives in the `shakomako/` folder of the Chatvia repository, but it is a
separate package: it imports nothing from the app, so this folder also works
as a project on its own. The folder is also published, with its own history,
as a separate read-only repository for people who want only the widget. Clone
either one, but open issues and pull requests in
[Chatvia](https://github.com/chatvia/chatvia): pull requests on the
read-only copy are closed automatically.

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

Every setting is optional. A build with no `.env` works wherever a Chatvia
server serves it:

- the loader (`embed.js`) loads the other files from its own folder;
- the widget uses the `/widget` API on the server `embed.js` came from;
- the widget gets the live-chat (Reverb) settings from that server, with the
  agent settings.

This is how a Chatvia server builds it: `npm run shakomako:build` in the
Chatvia repository puts the build in `public/shakomako`.

Set values in `.env` (copy `.env.example`) when the widget runs somewhere
else, for example on the dev server or a CDN:

| Variable | Meaning | When it is not set |
|---|---|---|
| `VITE_API_URL` | The widget API, for example `https://chat.example.com/widget`. | `/widget` on the server `embed.js` loads from. |
| `VITE_SCRIPT_URL` | Where `embed.js`, `version.json` and the release folders are served from. | The folder `embed.js` loads from. |
| `VITE_REVERB_APP_KEY`, `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, `VITE_REVERB_SCHEME` | The live-chat server. | The values the server sends. Without a host there, the API's own server. |
| `VITE_APP_VERSION` | The name of the release folder. | The package version plus a hash of the sources. |

The API endpoints are added to the API URL, for example `{api}/sessions/init`,
`{api}/conversations`, `{api}/agents/verify` and `{api}/stream`.

### Point a built widget at your own server

You can also set the server on the embed script tag. These optional attributes
win over the build values and the server values:

| Attribute | Example |
|---|---|
| `data-api-url` | `https://chat.example.com/widget` |
| `data-reverb-host` | `ws.example.com` |
| `data-reverb-port` | `443` |
| `data-reverb-key` | `your-reverb-app-key` |
| `data-reverb-scheme` | `https` or `http` |

```html
<script id="chatbot-embed-script"
    src="https://cdn.example.com/shakomako/embed.js"
    data-agent-id="YOUR_AGENT_ID"
    data-api-url="https://chat.example.com/widget"
    defer></script>
```

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

To create optimized static files for deployment:

```bash
npm run build
```

This generates:

```
dist/
  ├── embed.js               # Small loader script (sites embed this)
  ├── version.json           # Current release, read by embed.js
  └── {VERSION}/             # Release folder (e.g. 1.0.0-2730bfd924/)
      └── widget.min.js      # The widget bundle (bubble and bar modes)
```

Every change to the sources gives a new release folder name, so release
folders can stay in caches for a long time. Production builds also write `.gz`
and `.br` copies next to each file.

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

A Chatvia server builds and serves the widget itself (see "The chat widget"
in its self-hosting guide). CI (`.github/workflows/shakomako.yml` in the
Chatvia repository) lints, tests and builds the widget on every change under
`shakomako/`. It does not publish it.

To host the widget somewhere else, build it and upload the contents of `dist/`
to any static host or CDN. Point it at your Chatvia server with `VITE_API_URL`
or `data-api-url`. The host must send `Access-Control-Allow-Origin: *`,
because `embed.js` reads `version.json` with `fetch()` from other sites.

## License

Shakomako is released under the MIT license. See [LICENSE](LICENSE).

