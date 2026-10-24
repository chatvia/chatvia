Spectra keeps a log of every call this install makes to an AI service: which model answered, how many tokens it used, what it cost and how long it took. Admins signed in to this panel open it with the "Open Spectra" link, or from **Tools**.

- **Record AI calls** — Spectra logs new calls while this is on. It is on until you turn it off.
- **Keep AI calls for (days)** — Older calls are removed every night. The default is 30 days.

Spectra keeps its log in a database of its own, apart from the app's data. Turning it off stops new calls from being logged. What is already there stays until it is removed by age.

When the switch cannot be changed, the server's `.env` file has `SPECTRA_ENABLED=false`, which leaves Spectra out of this install.

**Tip:** Choose what Spectra saves of each call in **What Spectra saves**.
