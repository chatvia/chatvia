# Data Classification & Handling Policy

| | |
|---|---|
| **Owner** | Founder / Security Lead |
| **Version** | 1.0 |
| **Effective date** | 2026-06-09 |
| **Review cadence** | Annual (or on material change) |
| **Classification** | Internal |

## 1. Purpose

This policy defines how Chatvia, operated by Akkadian (Denmark, Skovbrynet 50, 1 tv, Næstved, Denmark), classifies the data it holds and the minimum handling, storage, and transmission rules for each class. Chatvia is an embeddable AI chatbot/agent SaaS: a JavaScript widget runs on customer websites, collects EU visitor PII, and turns captured contacts into **Leads**. For the personal data flowing through that widget, Chatvia acts as a **data processor**; its customers (workspaces) are the **controllers**. Correct classification ensures controls scale to sensitivity and that processor obligations are demonstrably met.

## 2. Scope

Applies to all data Chatvia stores or transmits across its production estate — Postgres (pgvector + bm25), Redis, Reverb, Horizon, and Scaleway S3-compatible object storage — all hosted in the EU: Hetzner (Germany) for compute, database, and Redis; Scaleway (France) for AI inference, object storage, backups, and email — plus data sent to Scaleway for LLM inference (open-weight Qwen3 models). It binds the founder, any contractors, and all automated processes (jobs, listeners, queue workers).

## 3. Classification levels

Chatvia uses four levels. When data of mixed sensitivity is combined, the record inherits the **highest** level present.

| Level | Definition | Default exposure |
|---|---|---|
| **Public** | Intended for unrestricted disclosure. | Internet |
| **Internal** | Operational data not intended for the public; low harm if leaked. | Authenticated staff |
| **Confidential** | Customer/business data causing material harm if disclosed. | Need-to-know, tenant-scoped |
| **Restricted** | Secrets and regulated PII; severe harm if disclosed. | Strict least-privilege, encrypted |

## 4. Chatvia data inventory and classification

| Data asset | Class | Store of record | Notes |
|---|---|---|---|
| Marketing site, public docs, widget bundle (JS) | **Public** | CDN / `public/` | No PII; integrity-protected. |
| Application logs, metrics, traces (Flare, Pulse, Telescope, Nightwatch) | **Internal** | Vendor / Postgres | Must not carry raw Restricted values. |
| `agent_activity` tool-call audit (spatie/activitylog) | **Internal** | Postgres | Immutable; integrity is the priority. |
| Conversation transcripts (chat content) | **Confidential** | Postgres | Processor data; sent to Scaleway for inference. |
| Knowledge-base documents (uploaded sources) | **Confidential** | Scaleway S3 (EU) + Postgres | Customer IP; may contain embedded PII. |
| Vector embeddings (pgvector) | **Confidential** | Postgres | Derived from transcripts/docs; treated as the source they encode. |
| Data exports (`DataExport` subsystem) | **Confidential** | Scaleway S3 (EU) | Time-boxed; pruned by `PruneExpiredDataExports`. |
| **Lead PII** (captured visitor contact data) | **Restricted** | Postgres | EU personal data; controller-owned. |
| End-user / workspace credentials (bcrypt hashes) | **Restricted** | Postgres | Never recoverable; hash only. |
| API keys, OAuth tokens (connectors/MCP) | **Restricted** | Postgres (encrypted) | App-level encryption via `APP_KEY` (AES-256). |
| Session payloads | **Restricted** | Postgres | `SESSION_ENCRYPT=true`. |

Lead PII is **Restricted** rather than Confidential because it is regulated EU personal data held under a processor obligation: a breach triggers GDPR notification duties to controllers and supervisory authorities.

## 5. Handling rules per class

Each level inherits the rules of every level above it in the table.

### 5.1 Public
- May be served to anyone over TLS; never mix with higher-class data on the same surface.
- Integrity-controlled: changes ship only via PR + CI (Pint, Larastan, ESLint, Pest) and tagged release; no ad-hoc edits to deployed `public/` assets.

### 5.2 Internal
- Access limited to authenticated staff (founder/contractors).
- Logs and monitoring (Flare, Pulse, Telescope, Nightwatch) must **scrub or omit** Restricted values — no raw tokens, passwords, or full Lead PII in log lines or breadcrumbs.
- The `agent_activity` audit log is **append-only**; no application code path updates or deletes rows except the scheduled retention prune (`PruneAgentActivity`).

### 5.3 Confidential
- **Tenant isolation is mandatory.** Every read/write is scoped by the owning workspace through `UserType` (Admin/Workspace/Employee/Chatter), per-resource Policies, and `TeamMembership`. No cross-tenant query path is permitted.
- **Inference data flow:** transcript content sent to Scaleway for Qwen3 inference stays within the EU; no other LLM provider receives this data. The processing record must list Scaleway as the sole inference sub-processor.
- Knowledge-base documents and exports are stored in Scaleway S3 (EU) with private ACLs and time-limited signed access only.
- Embeddings are governed as the data they encode — they are not treated as anonymous derivatives.
- Subject to plan-based retention (90 / 180 / 366 days, `config/billing.php`) enforced by scheduled prune jobs.

### 5.4 Restricted
- **Least privilege, encrypted always.** Lead PII, credentials, API keys, OAuth tokens, and session payloads are accessible only to the running application under tenant scope — never browsed casually, exported to spreadsheets, or pasted into chat/issue trackers.
- **Storage encryption:** API keys/OAuth tokens use Laravel app-level encryption (`APP_KEY`, AES-256); sessions encrypt at rest (`SESSION_ENCRYPT=true`); passwords are bcrypt hashes (irreversible). Plaintext secrets are never persisted.
- **No secrets in Git.** Enforced by GitHub secret scanning and `main` branch protection; credentials live only in deployment-time environment variables.
- **Lead PII is processor data.** Disclosure, deletion, and export occur only on controller instruction via the `DataExport` / lead-export paths; ad-hoc dumps are prohibited.
- Access to the production database for Restricted data requires a documented operational reason and is logged.

## 6. Transmission rules

| Class | In transit |
|---|---|
| Public | TLS (HSTS enforced). |
| Internal | TLS; staff endpoints behind authentication. |
| Confidential | TLS end-to-end, including Postgres (`DB_SSLMODE=require` in production) and the Scaleway inference/storage calls. |
| Restricted | TLS only; secrets transmitted solely as bearer tokens over encrypted channels; never emailed, never in URLs, never in logs. |

Web responses carry CSP, HSTS, `X-Frame-Options`, and `X-Content-Type-Options` headers via the `ContentSecurityPolicy` middleware; per-route rate limiting throttles enumeration and scraping of Confidential/Restricted endpoints.

## 7. Retention and disposal

- Transcripts and related Confidential data are retained per the workspace plan window (90 / 180 / 366 days) and removed by scheduled prune jobs.
- Data exports expire and are deleted by `PruneExpiredDataExports`; `agent_activity` is pruned by `PruneAgentActivity`.
- On controller request or account termination, Chatvia deletes or returns processor data per the Data Processing Agreement.
- Disposal of derived data (embeddings, exports) follows the source it encodes.

## 8. Roles and the solo-founder compensating control

Chatvia operates with a sole engineer and occasional contractors, so classic segregation of duties between author, reviewer, and deployer is not available. The **documented compensating control** is: all changes flow through GitHub PRs; CI runs Pest, `composer audit`, `npm audit`, Pint, Larastan, and ESLint on every push/PR (`.github/workflows/tests.yml`); production deploys are gated on the test job and ship only on `v*` tags (`.github/workflows/deploy.yml`); `main` branch protection, secret scanning, and weekly Dependabot updates apply; and the immutable Git history plus the `agent_activity` audit trail provide after-the-fact accountability reinforced by periodic self-review. The planned Filament admin panel will enforce staff 2FA (TOTP + passkeys, already available via `config/fortify.php` / `settings/two-factor`) for any privileged data access.

## 9. Exceptions and enforcement

Any deviation must be documented and approved by the Security Lead with a remediation date. Violations are handled under the Acceptable Use and Incident Response policies. Suspected misclassification or exposure of Confidential/Restricted data is reported to info@chatvia.ai; data-subject and privacy matters to info@chatvia.ai.

## 10. Mapping

**SOC 2 (Trust Services Criteria):**
- **CC6.1 / CC6.3** — logical access, least privilege, tenant isolation (`UserType`, Policies, `TeamMembership`); encryption of Restricted data.
- **CC6.6 / CC6.7** — boundary protection and encryption in transit (TLS, `DB_SSLMODE=require`, security headers).
- **CC3.2 / CC5.2** — risk-based classification driving control selection.
- **CC7.2** — audit logging (`agent_activity`, Flare, Pulse).
- **CC8.1** — change management (PR + CI + tagged deploys).
- **C1.1 / C1.2** — confidentiality: handling and disposal of Confidential data.
- **P4.1 / P4.3 / P5.1** — privacy: retention, disposal, and access/correction of Lead PII (processor role).

**ISO/IEC 27001:2022 Annex A:**
- **A.5.12** Classification of information; **A.5.13** Labelling of information.
- **A.5.10** Acceptable use; **A.5.14** Information transfer.
- **A.5.15 / A.5.18** Access control and access rights.
- **A.8.10** Information deletion; **A.8.12** Data leakage prevention.
- **A.8.24** Use of cryptography; **A.5.34** Privacy and protection of PII.
- **A.5.23** Information security for use of cloud services (Hetzner, Scaleway).

> Template prepared 2026-06-09. Review with qualified legal/security counsel before relying on it externally.
