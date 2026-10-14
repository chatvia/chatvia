A reranker reads each search result next to the question and gives it a score: how well it answers. Results and tools with a low score are dropped, so unrelated text does not reach the agent, and a message like "hello" gets none.

- **Reranker** — Which service does it. "Automatic" uses OpenRouter when it is connected, else Cohere when it is, else none.
    - _OpenRouter_ and _Cohere_ use their keys from **Settings → AI providers**.
    - _Self-hosted_ is your own server with the common rerank API (vLLM, Infinity and others), for example to run the open Qwen3 Reranker on your own hardware.
    - _Off_ uses a basic similarity check instead: results whose meaning is far from the question are dropped. It is free, but less exact than a reranker.
- **OpenRouter model** or **Cohere model** — The reranking model. The strongest come first. Each model scores in its own way, so each has its own minimum score (**Settings → Knowledge**, under **Advanced**).
- **Address**, **API key**, **Model** (self-hosted) — Where your server runs, its key if it asks for one, and the model it serves.

If the reranker fails, the agent uses the basic similarity check for that message, and still answers.

**Tip:** Not sure? Connect OpenRouter and keep "Automatic".
