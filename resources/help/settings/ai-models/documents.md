How the files people upload as knowledge are read.

- **Document reading** — The service that reads PDFs, Word, PowerPoint and images while sources are trained, with their scans and tables.
  - **Mistral OCR** also reads the pictures inside Word and PowerPoint files. Mistral downloads each file from a short-lived signed link, so this site must be reachable from the internet (or files must be kept in S3-compatible storage).
  - **Azure Document Intelligence** skips the pictures inside Word and PowerPoint files. Use the standard tier (S0): the free tier (F0) reads only the first 2 pages of each file, and files up to 4 MB.
  - **Off** — PDF, Word, slides and images cannot be uploaded.
  - **Automatic** uses Mistral when it is connected, else Azure.
- Excel, CSV, old office files (.doc, .ppt), RTF, text, Markdown and ebooks are always read by the app itself. Excel keeps its tables this way; Azure would lose them.
- PDFs locked with a password are refused at upload: no reader can open them.
- **Whole-PDF reading** — Writes source summaries and answers questions about PDF files in a chat. "Automatic" uses Mistral when it is connected. "Off" turns both off.
- **Mistral OCR model** (under **Advanced**) — Leave it blank for the default.

**Tip:** Settings → Knowledge → File types shows which service reads each file type.
