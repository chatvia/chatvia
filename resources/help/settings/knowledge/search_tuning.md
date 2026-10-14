Fine control over search. The defaults suit most agents.

- **Search mode** — **Balanced**, **More accurate** or **Faster**. It only matters for agents with very large sources (the note under the field gives the size). Smaller agents are always searched in full.
- **Most tokens per question** — The most text the results of one question may add, in tokens. Lower-ranked results past it are left out; the best one always goes in. 0 means no limit.
- **Leave out weak matches** — Results that do not answer the question well are left out, so a message like "hello" gets none. When off, agents always get the top results, even when nothing really matches.
    - With a reranker (Settings → AI models), each result gets a score, and results below **Minimum score** are left out.
    - Without one, a basic check leaves out results whose meaning is far from the question (**Minimum similarity**).
    - If the reranker fails, the basic check is used for that message, and the agent still answers.
- **Minimum score (reranker)** — From 0 to 1. Higher keeps fewer, closer results. Empty uses the value set for the reranking model.
- **Minimum similarity (no reranker)** — How close in meaning, from 0 to 1. Empty uses the value measured for your embedding model; a model never measured gets no check.
- **Saved answers: minimum similarity** — A message gets a saved Q&A answer when it is one of the saved questions (case and punctuation do not matter), or this close to one in meaning. Higher is safer, because the saved answer is sent word for word. Empty uses the value measured for your embedding model; when none is known, only whole questions match.

**Tip:** If agents say they do not know, but the answer is in their sources, try a lower minimum. If they use unrelated text, try a higher one.
