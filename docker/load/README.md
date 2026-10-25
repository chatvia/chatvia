# Load testing

Find out how many chats your server can answer at once before visitors do.
Add simulated users until something slows down or fails, fix that, and
try again.

## What you need

- [k6](https://k6.io/docs/get-started/installation/) on the machine that
  sends the load (not the server under test).
- An agent to test, and its external id (from the agent's URL).
- API tokens (Settings → API). The API answers 30 chat messages a minute
  per token, so use about one token per 3 simulated users.

## Use a test model, so the run costs nothing

`mock-llm.mjs` answers like an OpenAI-compatible provider (chat and
embeddings) after a short, random wait:

```bash
node docker/load/mock-llm.mjs
```

In the admin panel, add a custom (OpenAI-compatible) provider with the base
URL `http://<host>:8089/v1` and any key, add a model on it, and give the test
agent that model. Use a separate test agent: the mock answers every question
the same way. To measure a real provider's limits instead, skip the mock and
expect to pay for the run.

## Run

```bash
k6 run -e BASE_URL=https://chatvia.example.com -e AGENT=<agent id> -e TOKENS=<t1,t2,t3> -e USERS=20 docker/load/chat.k6.js
```

Other options: `RAMP` (time to reach `USERS`, default `1m`), `HOLD` (time at
`USERS`, default `3m`) and `QUESTIONS` (questions separated by `|`). The run
fails when more than 2% of the calls fail or the slowest 5% of replies take
more than 15 seconds.

## Watch while it runs

- **Horizon** (`/horizon`): the `messages` queue must not grow without
  end. If it does, add workers (admin Settings → Platform).
- **Pulse** (`/pulse`): slow requests, slow queries and server load.
- **Spectra** (`/spectra`): the time each AI call takes. A provider that
  turns calls away with "429" needs a higher limit with that provider; the
  app already waits and asks again a few times.

Start small (5 users), then double the number until replies slow down.
Stop the run quickly: a long run at full load can be expensive with a real
provider.
