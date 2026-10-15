How the files people upload as knowledge are read.

Each list shows whether a service is connected. A service that is not connected is greyed out: add its key in **AI providers** first. When you pick a new service and save, the app calls it once to check that it works. If the call fails, nothing is saved and the error says why.

- **Document reading** — The service that reads PDFs, Word, PowerPoint and images while sources are trained, with their scans and tables.
    - **Mistral OCR** also reads the pictures inside Word and PowerPoint files. Mistral downloads each file from a short-lived signed link, so this site must be reachable from the internet (or files must be kept in S3-compatible storage). The check on save reads a small test image.
    - **Azure Document Intelligence** skips the pictures inside Word and PowerPoint files. Use the standard tier (S0): the free tier (F0) reads only the first 2 pages of each file, and files up to 4 MB.
    - **Off** — PDF, Word, slides and images cannot be uploaded.
- Excel, CSV, old office files (.doc, .ppt), RTF, text, Markdown and ebooks are always read by the app itself. Excel keeps its tables this way; Azure would lose them.
- PDFs locked with a password are refused at upload: no reader can open them.
- **Summaries and PDF chat** — Mistral's models read each document whole. They write a short summary of every source, and answer questions about PDF files attached in a chat. **Off** turns both off. Only Mistral can do this.
- Under **Advanced** — The Mistral model names, typed by hand. Leave them blank for the defaults.
    - **Mistral OCR model** — Reads the documents.
    - **Summary model** — Writes the short summary of each source.
    - **Analysis model** — Finds each document's type, language, audience and likely questions.
    - **PDF chat model** — Answers questions about PDF files attached in a chat. It must read PDFs and use tools.

**Tip:** Settings → Knowledge → File types shows which service reads each file type.
