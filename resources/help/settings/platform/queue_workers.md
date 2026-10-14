Queue workers are background processes that do slow work, so people do not have to wait. Horizon runs them. Each number is the most workers of that kind that may run at the same time. More workers finish work faster, but each one uses about 80 MB of memory.

- **Chat** — Replies on messaging apps, emails and other short jobs. It cannot be lower than 2.
- **Files and websites** — Reading knowledge sources (files and web pages), then exports.
- **Analytics** — Conversation analysis, evals (automatic tests of agent answers) and audit logs.
- **Audio and video** — Turning recordings into text. Each one can take a long time.
- **Live voice calls** — Each voice conversation holds one worker for its whole length, so this is also the most calls at once.

Changes apply only on a live (production) install, such as the Docker setup, and only after Horizon restarts.

**Tip:** Raise a number when that kind of work waits too long. First check that your server has enough free memory.
