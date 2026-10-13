Google Gemini can make embeddings (number codes that let the agent find text by meaning). It is used only when Embeddings is "Google Gemini" on the Services card.

- **API key** — Create it in Google AI Studio ("Open console"). It is stored encrypted and never shown again. Leave the field blank to keep the saved key. Without a key, Gemini embeddings do not work.
- **Address** — The Gemini web address. Leave it blank to use the default.
- **Embedding model** — The model that makes the embeddings. Leave it blank to use the default. It is locked once sources are trained.

This key does not add chat models. Gemini chat models are set up separately, as a chat provider.

**Tip:** With Gemini embeddings, "Automatic" reranking is off. Pick a reranker on the Services card if you want one.
