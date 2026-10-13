Security headers are extra instructions the app sends to the browser with each page. The main one is the Content Security Policy: rules about what a page may load and do. They help protect people against attacks.

- **Content Security Policy** — Choose one:
  - **Report only**: the browser only reports what the rules would block. Nothing is blocked, so no page can break.
  - **Enforce**: the browser follows the rules. Other sites can no longer show your pages inside their own pages (the hosted chat can still be embedded). On a live site over HTTPS, browsers also learn to always use HTTPS for this site and its subdomains, and they remember this for a long time.
  - **Off**: no security headers at all.
- **Report address** — Optional. An address that receives the browsers' reports about blocked content. Leave it empty if you do not collect reports.

**Tip:** Start with Report only. Switch to Enforce when the site works well over HTTPS.
