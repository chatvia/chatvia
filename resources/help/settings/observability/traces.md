A trace groups the AI calls that belong together, such as the search, the tool calls and the answer of one reply. Spectra shows the calls of a trace as one chain.

- **One trace per request** — Every AI call of one page request, job or command shares a trace, and so do the jobs it starts. Off gives each call a trace of its own, and the chat log can no longer link a reply to its calls.
- **Join incoming traces** — When a request comes with a W3C `traceparent` header, as from an OpenTelemetry proxy or gateway, its AI calls join that trace.
- **Keep the request ID** — Saves the `X-Request-ID` header of the page request with each call, so you can find the call in the server logs.

**Tip:** Leave these on unless you have a reason to change them.
