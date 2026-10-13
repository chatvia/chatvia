Cloudflare Turnstile is a bot check. It stops automated programs (bots) from using the sign-up, sign-in and password reset forms. Most people never see a puzzle. Get both keys in your Cloudflare account. The "Open console" link takes you to the Turnstile page there.

- **Site key** — The public key. It shows the check on the form.
- **Secret key** — The private key. The server uses it to confirm the check with Cloudflare. It is stored encrypted and never shown again. Leave the field blank to keep the saved key.
- **Check sign-up**, **Check sign-in** and **Check password reset** — Choose which forms ask for the check: creating a workspace account; signing in with a password or asking for a sign-in link; asking for a password reset link.

The check works only when both keys are saved. If a key is empty, no form is checked.

**Tip:** Turn on the sign-in check if you see many failed sign-in attempts.
