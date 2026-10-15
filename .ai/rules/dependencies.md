---
paths:
  - composer.json
  - package.json
---

# Dependencies

## Check what you already have
Before adding a package, check whether Laravel or an installed package already does the job. Call a simple HTTP API with `Http::` inside one service instead of pulling in a vendor SDK.

## Wrap every SDK
A vendor SDK is imported by one service class only; the rest of the code uses that service.

## Pin with reasons
Use caret constraints; never `dev-main` or `*`. Dev tools go in `require-dev` / `devDependencies`. A held or pinned version needs a reason comment in `.github/dependabot.yml`.
