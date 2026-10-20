The app runs some programs on this server to read uploaded files and to make PDFs. You only need the programs for the file types you use. Without some of them the app does the work itself, so a host that does not let you install programs still works.

- **pdfinfo** (poppler) — PDF files: it counts their pages and finds locked ones. Without it, the app does this itself.
- **pdftotext** (poppler) — Only a backup. The document reader (Mistral OCR or Azure, in Settings → AI models) reads PDF files. pdftotext reads a PDF only if it is trained again after the reader is turned off.
- **pandoc** — RTF files. No document reader reads them, so without pandoc they cannot be uploaded. It also reads EPUB books and counts the pages of Word (.docx) files. Without it, the app reads EPUB books itself, and the page count is an estimate. FB2 books are always read by the app itself.
- **catdoc** and **catppt** — Old Word (.doc) and PowerPoint (.ppt) files. Mistral OCR and Azure do not read them, so without these programs they cannot be uploaded.
- **ffmpeg** — Makes audio and video small enough for speech to text, or turns them into a format the service takes. Recordings the service takes as they are (for example an MP3 under 25 MB) need no ffmpeg. Needed only when speech to text is on.
- **ffprobe** — Measures how long audio and video files are. Without it, the app does this itself.
- **weasyprint** — Chat exports as PDF, and summaries of office files, when it is the **PDF maker** (the card above). With Cloudflare or Gotenberg as the PDF maker, it is not needed.

**Not found · not needed** means nothing stops working without the program now. **Not found** in orange says what does not work.

When the app is installed, it looks for each program the same way Detect does (below) and fills in the full path it finds. A program installed later is not filled in: click **Detect** for it.

Leave a field empty to find the program by its name in the PATH (the list of folders where the server looks for programs). If a program is not found, click **Detect**: it runs `which` on the server, also in the usual folders (such as /opt/homebrew/bin), and puts the full path it finds in the field. Then save. You can also type the full path yourself, such as /opt/homebrew/bin/pdfinfo.

For safety, a path must end in the program's own name. The panel does not accept the path of any other program.

"Found" means the web server finds the program. The queue worker (it reads uploaded files) may look in other folders. A full path works for both.

**Tip:** If Detect finds nothing, the program is not installed, or it is in a folder Detect does not know. Run `which pdfinfo` in a terminal on the server to find it.
