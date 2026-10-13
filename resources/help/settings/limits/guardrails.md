Guardrails are safety checks on chats. They hide personal data (such as email addresses, phone numbers and card numbers) and can stop prompt injection (messages that try to trick the agent), off-topic messages and toxic (rude or harmful) messages.

- **Guardrails** — The main switch for the whole install. When it is on, each agent uses its own guardrail settings. When it is off, no check runs on any agent, even if the agent has checks turned on, and nothing new is logged.
- **Days to keep the guardrail log** — Each time a check finds something, it is saved in a log. Entries older than this are deleted once a day.

**Tip:** Keep guardrails on. Turn them off only for a short time, for example to find the cause of a problem.
