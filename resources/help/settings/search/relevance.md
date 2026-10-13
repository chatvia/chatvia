After a search, an AI service can give each result a score: how well it answers the question. Results with a low score are dropped, so unrelated text does not reach the agent.

- **Relevance check** — When on, results that score below the minimum are dropped. A message like "hello" then gets no results. When off, agents always get the best results, even when nothing really matches. This needs a reranker (a service that scores results), chosen under Settings → AI with the **Reranking** option. Without one, both fields here are locked. If the reranker fails, the agent gets the usual results without the check.
- **Minimum relevance** — A number from 0 to 1. A result needs at least this score to be kept. Higher keeps fewer, closer results. Lower keeps more.

**Tip:** If agents say they do not know, but the answer is in their sources, try a lower minimum. If they use unrelated text, try a higher one.
