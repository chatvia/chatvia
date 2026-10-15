When a source is trained (read and prepared for the agent), its text is cut into chunks (small parts). An AI model can add helpful notes to each chunk, so agents find the right part more easily. This costs model calls.

- **Enhance files and web pages** — Adds a short summary and the questions each chunk likely answers, for uploaded files and imported web pages. For documents, it also describes the pictures and tables on each page. The whole source gets a summary too. Agents then find far more of the right passages, so it is on by default. It makes one model call per chunk while training, so large files cost more: a 300-page PDF makes about 300 calls.
- **Enhance text sources** — The same step for texts that people type or paste. These are short, so it costs little.

With both off, training makes no model calls. The notes come from the **AI enhancement** model, tables from the **Tables** model and pictures from the **Image descriptions** model (Settings → AI models → Models for app tasks). A change applies to sources trained after you save.

**Tip:** Not sure? Keep the defaults.
