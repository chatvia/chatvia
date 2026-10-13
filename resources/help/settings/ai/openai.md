OpenAI can make embeddings (number codes that let the agent find text by meaning) and turn speech into text. Embeddings are used only when Embeddings is "OpenAI" on the Services card, and speech to text when Speech to text is "OpenAI" (or "Automatic" with no Scaleway key).

- **API key** — Create it on the OpenAI platform ("Open console"). It is stored encrypted and never shown again. Leave the field blank to keep the saved key. Without a key, OpenAI embeddings do not work.
- **Address** — The OpenAI web address. Leave it blank to use the default.
- **Embedding model** — The model that makes the embeddings. Leave it blank to use the default. It is locked once sources are trained.
- **Speech-to-text model** — The model that turns voice input, audio and video into text: `whisper-1` by default, or `gpt-4o-transcribe`. OpenAI takes files up to 25 MB, about an hour and a half of audio.

This key does not add chat models. OpenAI chat models are set up separately, as a chat provider.

**Tip:** With OpenAI embeddings, "Automatic" reranking is off. Pick a reranker on the Services card if you want one.
