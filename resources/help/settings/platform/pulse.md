Pulse shows how this install is doing: slow pages, slow database queries and jobs, errors, the queues, and the CPU, memory and disk of the servers. Admins signed in to this panel open it with the "Open Pulse" link, or from **Tools**.

- **Record app health** — Pulse collects new data while this is on. Turn it off to stop collecting. What Pulse already has stays on its page until it is 7 days old.

Pulse keeps its data in a database of its own, apart from the app's data.

When the switch cannot be changed, the server's `.env` file has `PULSE_ENABLED=false`, which leaves Pulse out of this install.

**Tip:** Leave it on. It shows problems early, often before anyone reports them.
