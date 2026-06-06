# File sources

Upload your team's documents, audio, and video. Chatvia extracts the text and indexes it so the agent can answer questions grounded in your business content — SOPs, contracts, manuals, runbooks, onboarding decks, recorded calls.

![SCREENSHOT — file sources tab](/screenshots/sources-files.png)

## Supported formats

| Category | Formats |
| --- | --- |
| **Documents** | PDF, DOC, DOCX, TXT, RTF, HTML, Markdown |
| **Spreadsheets** | XLS, XLSX, CSV, ODS |
| **Presentations** | PPT, PPTX, ODP |
| **Images** | JPG, PNG, GIF, BMP, SVG, TIFF, WEBP — text is extracted via OCR |
| **Audio** | MP3, WAV, M4A, WEBM — transcribed |
| **Video** | MP4, AVI, MOV, WMV — transcribed |

Limits:

- **Document size** — typically up to 100 MB per file. Long contracts and multi-hundred-page manuals are fine.
- **Audio/video** — up to 20 MB per file. Useful for recorded training sessions and meeting clips.
- **Image OCR** — works best on screenshots and scans; photos of complex layouts are hit-or-miss.

## Uploading

Drag files onto the dropzone or click to pick. Multiple files at once is fine — your team can bulk-upload a whole policy folder. Each file becomes its own source.

While processing, your team will see:

- A progress indicator while the file uploads.
- A spinner while the background ingestion job parses, chunks, and embeds.
- A green check when the source is ready.

If a file fails (encrypted PDF, unsupported variant, no extractable text), the source goes to **Failed** and the teammate who uploaded it can hover for the error. Re-upload after fixing the source — failed uploads don't consume credits.

## Markdown sources

Markdown files are treated specially: Chatvia preserves headings and code blocks during chunking so the model gets cleaner context. Use Markdown for hand-authored content (internal style guides, engineering wikis, onboarding handbooks).

## Audio and video

Audio and video files are transcribed first, then indexed like any other text. The transcript is stored with the source so your team can see exactly what was extracted (handy for debugging or auditing internal training recordings).

::: tip Long videos
For videos longer than 20 minutes, prefer [YouTube sources](/guide/sources/youtube) over direct upload — YouTube's transcripts are usually higher quality than ours, and the file size limit doesn't apply.
:::

## Metadata and filtering

Every file source carries:

- Original filename and size.
- Detected language.
- Document classification (article, contract, manual, etc.) — visible in [Analytics → Sources](/guide/chat/analytics).
- Retrieval count — how often this source's chunks have been served to chatters.

Your team can filter the list by status, language, or filename.

## Retraining

Click a source → **Retrain** to re-ingest from the current file. Use this when your team patches a typo or restructures the document and you want the chunks rebuilt.

## Deleting

Delete removes the source, its chunks, and the underlying file. Past conversations that referenced it stay intact (they're stored verbatim), but new questions won't be able to retrieve from it.

## Files via API

Your team can upload files programmatically — handy for CI pipelines that re-ingest internal documentation after a release. See the [API reference](https://chatvia.ai/docs/api) under **Sources**.
