The app runs some programs on this server to read uploaded files and to make PDFs. You only need the programs for the file types you use.

- **pdfinfo** and **pdftotext** (poppler) — PDF files.
- **pandoc** — Word (.docx), ODT, RTF, EPUB and FB2 files.
- **catdoc** and **catppt** — Old Word (.doc) and PowerPoint (.ppt) files.
- **ffmpeg** and **ffprobe** — Audio and video files.
- **weasyprint** — Chat exports as PDF, and summaries of office files.

Leave a field empty to find the program by its name in the PATH (the list of folders where the server looks for programs). If a program is not found, click **Detect**: it runs `which` on the server, also in the usual folders (such as /opt/homebrew/bin), and puts the full path it finds in the field. Then save. You can also type the full path yourself, such as /opt/homebrew/bin/pdfinfo.

For safety, a path must end in the program's own name. The panel does not accept the path of any other program.

"Found" means the web server finds the program. The queue worker (it reads uploaded files) may look in other folders. A full path works for both.

**Tip:** If Detect finds nothing, the program is not installed, or it is in a folder Detect does not know. Run `which pdfinfo` in a terminal on the server to find it.
