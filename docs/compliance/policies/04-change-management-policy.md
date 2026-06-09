# Change Management Policy
| | |
|---|---|
| **Owner** | Founder / Security Lead |
| **Version** | 1.0 |
| **Effective date** | 2026-06-09 |
| **Review cadence** | Annual (or on material change) |
| **Classification** | Internal |

## 1. Purpose

This policy governs how changes to Chatvia's source code, infrastructure configuration, and production environment are proposed, reviewed, tested, and released. Chatvia processes EU visitor PII (conversation transcripts, Leads, uploaded knowledge-base documents) as a **data processor** on behalf of its customers. Uncontrolled change is the most likely route to an availability, confidentiality, or integrity failure affecting that data. Chatvia shall ensure every production change is traceable, reviewed, automatically tested, and reversible.

## 2. Scope

This policy applies to all changes to:

- Application source code (Laravel 13 / PHP 8.4 backend; Inertia + Vue 3 frontend; the embeddable widget).
- Database schema migrations (Postgres + pgvector + bm25).
- CI/CD pipeline definitions (`.github/workflows/`) and dependency configuration (`.github/dependabot.yml`).
- Infrastructure and runtime configuration hosted in the EU — Hetzner (Germany) for application servers, the self-hosted PostgreSQL database, Redis, Reverb websockets, and Horizon queues; Scaleway (France) for AI inference, S3-compatible object storage, backups, and transactional email.

Emergency/break-glass changes (Section 8) are in scope. Customer-side configuration (a workspace editing its own agent settings through the product UI) is **out of scope** — that is product behaviour, not a change to Chatvia.

## 3. Roles and the segregation-of-duties compensating control

Chatvia operates with **a single full-time engineer (the Founder)** plus occasional contractors. Classic segregation of duties — a separate author, reviewer, and deployer — cannot be staffed. Chatvia shall instead rely on the following **documented compensating controls**, which move the segregation from *people* to *enforced, auditable system gates*:

- **No direct pushes to production code.** Every change reaches `main` only through a GitHub Pull Request. Direct commits to `main` are blocked by branch protection (Section 4).
- **The CI gate is the independent reviewer.** No human can wave a change past failing tests, static analysis, or a dependency audit; the pipeline is the non-bypassable second party (Section 5).
- **Deployment is decoupled from authorship.** Production releases are triggered by an annotated, signed-where-possible Git tag and are gated on a fresh test run, not on the author's say-so (Section 6).
- **Immutable history.** All changes are recorded in Git with author, timestamp, PR discussion, and the CI run that approved them. History is append-only; force-pushes to `main` are disallowed.
- **Periodic self-review.** The Founder performs a documented quarterly review of merged changes, dependency-update PRs, and deploy logs to detect drift, unreviewed merges, or anomalous releases.

When the team grows, mandatory **second-person PR review** by a different individual shall replace the self-review compensating control.

## 4. Branch protection and version control

- `main` is the single source of truth for production. The protected `main` branch shall enforce:
  - Required status checks — the **Tests** workflow (`.github/workflows/tests.yml`) must pass before merge.
  - No direct pushes; changes land via PR only.
  - No force-pushes and no branch deletion.
  - Linear, append-only history.
- **GitHub secret scanning** is enabled on the repository to detect committed credentials, API keys, or OAuth tokens before they reach a release.
- All feature work happens on short-lived branches and is merged into `main` via PR.

## 5. Pull request review and the CI gate

Every PR targeting `main` (or `develop`) automatically triggers the **Tests** workflow on push and on pull request. The workflow runs on a clean runner with a disposable Postgres (pgvector/bm25) and Redis service and shall pass **all** of the following before a change is mergeable:

- **Automated test suite** — full Pest 4 suite via `php artisan test --parallel`, after `npm run build` and `php artisan migrate`, on the production PHP version (8.4). This includes the architecture tests that enforce Chatvia's layering and Action-coverage conventions.
- **Static analysis** — Larastan (PHPStan) for type/logic defects and ESLint for the Vue/JS frontend.
- **Code style** — Laravel Pint (PHP) enforces a consistent, reviewable diff.
- **Dependency vulnerability audit** — `composer audit` and `npm audit` fail the build on a known-vulnerable PHP or JS dependency, preventing a vulnerable supply-chain change from merging.

A PR shall be merged only when CI is green and the author (acting as reviewer under the Section 3 compensating control, or a second person once staffed) has reviewed the diff. CI status is a **required** check — a red build cannot be merged regardless of reviewer action.

## 6. Production deployment

- Production deploys are **tag-driven**. Pushing an annotated Git tag matching `v*` triggers the **Deployment** workflow (`.github/workflows/deploy.yml`).
- The deploy job **`needs: tests`** — it reuses the same Tests workflow via `workflow_call`. **If the test job fails, deployment does not run.** There is no manual override that skips the gate.
- Deployment targets the GitHub **`production` environment** (scoped secrets/variables) and uses a concurrency group (`production`, `cancel-in-progress`) so only one release runs at a time, preventing overlapping or racing deploys.
- Deployment to the Hetzner (EU) host is performed over SSH via Deployer using the tagged ref, giving an exact, reproducible mapping between a released version and the Git commit it came from.
- Database migrations run as part of the release. Schema changes shall be backward-compatible within a release window where practical (additive migrations preferred) so that rollback does not require destructive down-migrations.

## 7. Rollback

Chatvia shall be able to revert a faulty release quickly:

- **Application rollback** — because each release maps to an immutable tag/commit and Deployer keeps prior releases, the previous known-good tag can be redeployed via the same gated pipeline.
- **Forward fix** — for defects without data impact, a corrective change follows the normal PR → CI → tag flow.
- **Data/schema rollback** — dump-based backups via spatie/laravel-backup to the separate Scaleway bucket (planned) and the backup regime (per the Backup & Resilience policy) cover recovery from a destructive migration or data-corrupting change. Restores are validated by the quarterly restore test.
- A rollback is itself a change: it is recorded (which tag was rolled back, why) and reviewed in the next quarterly self-review.

## 8. Emergency changes

For a production-down or active-security incident where the full PR cycle would cause unacceptable harm:

- The fastest safe path is still **redeploying the last known-good tag** (Section 7) rather than an unreviewed hotfix.
- If code must change, the fix shall go through a PR and the CI gate even under time pressure; the gate is fast and is the cheapest insurance against making the incident worse.
- Any deviation (e.g. a manual server-side change) shall be documented at the time, reconciled back into Git within **2 business days**, and reviewed in the incident write-up.

## 9. Dependency and configuration change

- **Dependabot** (`.github/dependabot.yml`) opens grouped weekly update PRs for Composer, npm, and GitHub Actions. Each update flows through the same CI gate (tests + audits + static analysis) before merge — no dependency bump bypasses review.
- High-severity advisories surfaced by `composer audit` / `npm audit` are prioritised and patched promptly (see Vulnerability Management policy).
- CI/CD workflow and infrastructure-configuration changes are version-controlled and reviewed exactly like application code.

## 10. Records and review

- The authoritative change record is the Git history: commits, PRs, CI run results, and deploy workflow logs (retained by GitHub).
- The Founder reviews merged PRs, Dependabot activity, and deployment logs **quarterly**, and reviews this policy **annually or on material change** to the SDLC or hosting model.

## 11. Mapping

| Framework | Control reference |
|---|---|
| **SOC 2** | CC8.1 (change management — authorise, design, develop, test, approve, implement); CC6.1 / CC6.8 (logical access & unauthorised-change prevention via branch protection and gated deploys); CC7.1 (vulnerability detection via dependency audit + secret scanning); CC4.1 (monitoring via quarterly self-review) |
| **ISO/IEC 27001:2022 Annex A** | A.8.32 (Change management); A.8.31 (Separation of dev/test/prod via CI runner + production environment); A.8.25 (Secure development life cycle); A.8.28 (Secure coding — Pint/Larastan/ESLint gates); A.8.29 (Security testing in development — CI test suite); A.8.8 (Management of technical vulnerabilities — Dependabot + audit); A.8.4 (Access to source code); A.5.3 (Segregation of duties — compensating controls in Section 3) |

> Template prepared 2026-06-09. Review with qualified legal/security counsel before relying on it externally.
