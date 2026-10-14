{title} is an AI provider. Its key is entered once, here, and used for everything {title} does in this app. The small labels under the description say what it can do. The ones in use are marked.

- **API key** — Create it on the provider's site ("Get a key"). It is checked before it is saved, and stored encrypted. It is never shown again. Leave the field blank to keep the saved key. The trash button removes it: everything that uses this provider stops until you add a key again.
- **Address** (under **Advanced**) — The provider's web address. Leave it blank for the default. Change it only to go through a proxy, another region, or (for Scaleway AI) to bill one project.

After you add a key:

- Add the provider's chat models in **Settings → AI models**.
- Its other services (embeddings, speech to text, documents, reranking) work when **Automatic** is chosen on **AI models**, or when you choose this provider there.

**Tip:** One provider is enough to start. Scaleway AI, OpenRouter, OpenAI, Mistral and Google Gemini also make embeddings; with Groq you need a second provider for them.

**Azure Document Intelligence:** use the standard tier (S0). The free tier (F0) reads only the first 2 pages of each file, and files up to 4 MB; the connection check warns when it sees one.
