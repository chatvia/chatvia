A server of your own runs AI models with an OpenAI-compatible API: vLLM, Ollama, LM Studio, or another service. Add it here, then add its models in **Settings → AI models**.

- **Name** — How the server is listed.
- **Address** — The address of its API, usually ending in `/v1`. It is checked before the server is saved.
- **API key** — Only when your server asks for one. When you edit a server, leave it blank to keep the saved key.
- **Extra headers** (under **Advanced**) — Sent with every request, for servers that need them.

**Remove** takes the server's models off the list agents can use. Agents that answer with them move to the model you pick.

**Tip:** A server of your own is for chat models only. Search still needs an embedding service from a provider above.
