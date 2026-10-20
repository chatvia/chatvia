Chooses what makes the app's PDFs: chat exports, and the PDF of an office file that the app makes for its summary.

- **Make PDFs with** — **WeasyPrint** is a program on this server. The Docker image has it. If your host does not let you install programs, pick **Cloudflare Browser Rendering** or **Gotenberg** instead.
- **Account ID** and **API token** (Cloudflare) — Copy the account ID from your Cloudflare dashboard. Create the token under **My Profile → API Tokens**, with the **Browser Rendering: Edit** permission. **Get a token** opens that page. The token is stored encrypted and never shown again. Leave it blank to keep it.
- **Address** (Gotenberg) — The address of your Gotenberg server, such as http://gotenberg:3000.
- **Username** and **Password** (Gotenberg) — Only when your Gotenberg server asks for them.

When you save a new choice, the app makes a one-line test PDF with it. If that fails, nothing is saved and the error says why.

Pages are made without loading anything from the internet or your network: images and fonts in a page must be built in.

**Tip:** Run Gotenberg where it cannot reach your private network, or other servers inside it.
