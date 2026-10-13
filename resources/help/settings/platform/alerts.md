Horizon runs the background workers (processes that do slow work, like reading files or replying on messaging apps). Work waits in a queue (a waiting line) until a worker is free. This card sets where Horizon sends its warnings.

- **Alert email** — This address gets an email when jobs wait too long in the queue. Leave it empty to get no alert emails. A change applies after Horizon restarts.

Email sending must be set up (Settings → Email), or the alerts cannot go out.

**Tip:** Use an inbox that someone reads. Many alerts often mean you need more queue workers.
