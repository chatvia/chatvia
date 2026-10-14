Embeddings are number codes that let an agent find text by meaning. They are made for every source when it is trained, and for every question.

- **Service** — Which provider makes them. Its provider must be connected (**Settings → AI providers**), and it must make codes of the size this install stores, or saving fails.
- **Model** — The service's embedding model. Leave it blank for the default. For OpenRouter, pick one from its list: the default is OpenAI text-embedding-3-small.

Both are locked once a source is trained: changing them needs every source trained again.

**Test embeddings** (at the top of the page) makes one test embedding and checks the size.

**Tip:** Keep the default model unless you know the new one returns the same size.
