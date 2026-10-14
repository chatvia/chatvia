Voice conversations let visitors talk with an agent out loud, in the hosted chat and the chat widget. A button next to the microphone opens a live call. The agent answers in its own words and its own persona, looks things up in its knowledge, and can use its tools that do not need approval.

The list shows the service in use, and whether OpenAI is connected. Until it is, **OpenAI Realtime** is greyed out: add its key in **AI providers** first. When you turn voice on and save, the app calls OpenAI once to check the key. If the call fails, nothing is saved and the error says why.

- **Voice conversations** — **OpenAI Realtime**, a model that listens and speaks directly. **Off** hides the button everywhere. Each agent still has to turn it on in Setup → Settings → Composer options.
- **Default voice** — The voice agents speak with. An agent can pick its own. Marin and Cedar sound the most natural.
- **Longest call, in minutes** — The agent says goodbye and hangs up then. At most 25.
- **Model** (under **Advanced**) — The mini model costs less, but follows instructions and uses tools less well.
- **Transcription model** (under **Advanced**) — Writes down what the visitor says, so calls show in the chat log like typed chats.

The visitor's audio goes straight from the browser to OpenAI; it never passes through this server. Both sides of every call are saved as messages, and each spoken answer counts against the message limits.

Each live call uses one "Live voice calls" queue worker for its whole length (Settings → Platform → Queue workers). When they are all busy, a new call gets "Voice is busy right now".

**Tip:** A minute of voice costs much more than a typed message. Set message limits first, and keep the longest call short.
