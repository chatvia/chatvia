Embeddings are number codes that let an agent find text by meaning. They are made for every source when it is trained, and for every question.

- **Service** — Which provider makes them. Its provider must be connected (**Settings → AI providers**), and it must make codes of the size this install stores, or saving fails.
- **Model** — The service's embedding model. Leave it blank for the default. For OpenRouter, pick one from its list: the default is OpenAI text-embedding-3-small.

**Changing them after sources are trained.** Codes from two models cannot be compared, so agents cannot find your documents until everything is embedded again. The page warns you and asks before it saves. Saving starts a **re-index**: every document, Q&A and tool gets new codes from the new model. Files are not read again; only the codes are made again.

The re-index runs in the background queue, so it needs the queue workers running. With many documents it can take hours. Until it ends, agents may miss some documents. Its progress shows in this section.

**Re-index documents** (at the top of the page) starts a re-index by hand, for example when the last one could not finish some parts.

**Test embeddings** (at the top of the page) makes one test embedding and checks the size.

**Tip:** Keep the default model unless you know the new one returns the same size.
