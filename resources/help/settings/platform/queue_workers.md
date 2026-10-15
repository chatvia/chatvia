Queue workers are background processes that do slow work, so people do not have to wait. Horizon runs them. Each number is the most workers of that kind that may run at the same time. More workers finish work faster, but each one uses about 80 MB of memory.

- **Chat** — Replies on messaging apps, emails and other short jobs. It cannot be lower than 2.
- **Files and websites** — Reading knowledge sources (files and web pages), then exports.
- **Analytics** — Conversation analysis, evals (automatic tests of agent answers) and audit logs.
- **Audio and video** — Turning recordings into text. Each one can take a long time.
- **Live voice calls** — Each voice conversation holds one worker for its whole length, so this is also the most calls at once.

Changes apply only after Horizon restarts. Save your changes, then click **Restart queue** below the numbers. Until then the card says "Restart needed". When the queue is not running, the card says "Queue not running" and the button tells you there is nothing to restart: start the queue, and it uses the saved numbers. While a restart is going on, the card says "Queue restarting". The jobs that are running finish first, and new jobs wait until then, so a live voice call or a long recording can make the restart take several minutes. Docker starts the queue again on its own. On another server, Supervisor or systemd must start Horizon again.

**Tip:** Raise a number when that kind of work waits too long. First check that your server has enough free memory.
