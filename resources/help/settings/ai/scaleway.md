Scaleway AI runs the Qwen3 models. This app uses it for embeddings (number codes that let the agent find text by meaning), reranking (putting the best search results first) and speech to text.

- **API key** — Create it in the Scaleway console ("Open console"). It is stored encrypted and never shown again. Leave the field blank to keep the saved key. Without a key, these features do not work, and "Automatic" speech to text stays off.
- **Address** — The Scaleway web address. Leave it blank for the default. To bill one Scaleway project, use the project address shown under the field.
- **Embedding model** — Used when Embeddings is "Qwen3 on Scaleway AI" on the Services card. It is locked once sources are trained.
- **Rerank model** — Used when Scaleway does the reranking.
- **Speech-to-text model** — Turns voice input and audio into text.

This key does not add chat models. Scaleway chat models are set up separately, as a chat provider.

**Tip:** Leave the model fields blank to use the defaults shown in them.
