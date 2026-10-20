The models behind the app's own AI calls: the calls the app makes for you, not the answers of your agents. The defaults suit most installs. Hover the **?** beside a field to see what it does.

- **Use the recommended models** — Picks your chat provider's small, fast model for quick tasks, and your default model for the evaluation judge and translations. It clears the models you set for single tasks.
- **Model for quick tasks** — Every task without its own model uses this one. Empty means the default model for new agents. A small, fast model suits most tasks.
- **During a chat** — Follow-up questions, tool picking and guardrails run before the agent answers, so the person waits for them. Pick a small, fast model here.
- **After a reply** — Conversation titles and conversation analysis (topics, sentiment, tone) run in the background. Speed matters less.
- **Agent setup** — Conversation starters, translating agent texts, form descriptions and hosted chat addresses run when someone sets up an agent.
- **Training sources** — AI enhancement, tables and image descriptions run while sources are trained. They make one call per part of a file, so a cheap model saves the most here. Image descriptions need a model that reads images.
- **Testing** — The evaluation judge scores the answers of an agent in evaluations. A stronger model gives fairer scores.

**Tip:** Press **Use the recommended models**, or set **Model for quick tasks** to a small, fast model, and leave the rest empty. Give a task its own model only when it needs one.
