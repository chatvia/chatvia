Agents can call tools (actions such as a web search or a lookup in another app). Every call is saved in a log that workspaces can see. There is also a limit on how often an agent may call the same tool.

- **Calls per tool** — How many times one agent may call one tool in the time window. When the limit is reached, the call does not run. The agent gets a message that the limit was reached, and the log shows it.
- **Time window (seconds)** — How long that window is. It starts with the first call and starts again when the time is over.
- **Days to keep the tool-call log** — Log entries older than this are deleted once a day.

**Tip:** Lower the limit if an agent calls a tool again and again without a good reason. Keep more days if you need a longer history.
