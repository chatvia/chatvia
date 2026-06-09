# Logging & Monitoring Policy

| | |
|---|---|
| **Owner** | Founder / Security Lead |
| **Version** | 1.0 |
| **Effective date** | 2026-06-09 |
| **Review cadence** | Annual (or on material change) |
| **Classification** | Internal |

## 1. Purpose & scope

This policy defines what Akkadian ("Chatvia") logs, how it monitors its systems, how logs are protected and retained, and how monitoring signals are reviewed and acted on. The objective is to ensure that security-relevant events on the Chatvia platform are reliably recorded, tamper-resistant, and observed closely enough to detect and respond to anomalies.

Chatvia is the embeddable AI chatbot/agent SaaS hosted in the EU — Hetzner (Germany) for compute & database, Scaleway (France) for AI inference, storage & backups. It acts as a **data processor**; its customers (workspaces) are **controllers**. The systems in scope are production application servers (Laravel 13 / PHP 8.4), the Hetzner-hosted Postgres (pgvector + bm25), Redis, Reverb websockets, Horizon queues, Scaleway S3-compatible storage, Scaleway-hosted Qwen3 LLM inference, the GitHub/CI/CD pipeline, and the embeddable widget delivered to EU visitors.

**Team reality.** Chatvia is operated by a sole founder with occasional contractors. Log *review* therefore cannot be segregated from log *generation* by a second person. The documented compensating controls are: an **immutable, append-only audit trail** (`agent_activity` via spatie/activitylog) that the operator cannot silently alter through the application; logs shipped to **independent third-party tooling** (Flare, Scaleway) outside the operator's day-to-day write path; an **immutable Git history** of every change; and an automated weekly review cadence enforced by calendar (§6).

## 2. What is logged

Chatvia shall capture the following event classes:

### 2.1 Authentication & identity events
- Login success and failure, logout, and session lifecycle (Laravel Fortify + Sanctum).
- Two-factor enrolment, challenge, and recovery-code use (TOTP and passkeys, `settings/two-factor`).
- Password changes and resets; email-verification events.
- API key (Sanctum token) creation, use, and revocation; OAuth token issuance and refresh for connectors.
- Role and access changes — `UserType` (Admin/Workspace/Employee/Chatter) assignment and `TeamMembership` grants/revocations.

### 2.2 Administrative & privileged events
- Workspace, agent, and member create/update/delete operations.
- Subprocessor/connector configuration changes (OAuth client setup, MCP server registration).
- Production deployments — recorded immutably as `v*` tag pushes and gated CI runs in GitHub (`.github/workflows/deploy.yml`), not in the application.
- Data-export and account-deletion requests handled by the DataExport subsystem.
- Staff access to the planned Filament admin panel (which shall enforce staff 2FA).

### 2.3 Immutable agent tool-call audit (`agent_activity`)
Every LLM tool invocation through the agent runtime is recorded in the **`agent_activity`** table via **spatie/activitylog** (`Domain\Conversation\Models\AgentActivity`). Each record captures the tool name, the invoking agent/conversation, arguments and outcome, and a timestamp. This log is the authoritative, **append-only** record of what agents *did* with customer data and connectors. The application exposes it read-only (e.g. `Widget\Controllers\Activity`); there is no application path to edit a record. Unexpected tool names, argument patterns, or call volume are first-order security indicators (see Incident Response Plan §3.1).

### 2.4 Application, infrastructure & security events
- Unhandled exceptions and error spikes (Flare).
- Slow queries, queue health, request volume, and runtime anomalies (Laravel Pulse / Telescope / Nightwatch).
- Per-route rate-limit rejections and rejected requests at the web edge.
- CSP/HSTS and other header-policy context from the `ContentSecurityPolicy` middleware.
- GitHub secret-scanning alerts, Dependabot alerts, and CI security-job failures (`composer audit` / `npm audit`).
- Hetzner and Scaleway console events for compute, database, and storage.

### 2.5 What is NOT logged
To limit exposure of personal and secret data, Chatvia shall **not** write the following to application or diagnostic logs: plaintext passwords, full API keys / OAuth tokens / `APP_KEY`, raw 2FA secrets, or unredacted Lead PII and conversation transcripts in error payloads. Flare is configured to scrub sensitive request parameters. Conversation content lives in the encrypted application database, not in diagnostic logs.

## 3. Monitoring tooling

| Tool | Purpose | Signal type |
|---|---|---|
| **Flare** | Exception capture, error-rate alerting, release health | Errors, regressions |
| **Laravel Pulse** | Live runtime health — slow queries, jobs, requests, exceptions (dedicated `pulse` DB connection) | Performance / anomaly |
| **Laravel Telescope** | Deep request/job/query introspection for debugging (dedicated `telescope` DB connection) | Diagnostic |
| **Nightwatch** | Continuous application monitoring and trend visibility | Health / trend |
| **`agent_activity`** | Immutable tool-call audit | Security audit |
| **GitHub** | Secret scanning, Dependabot, CI gate results, branch protection | Supply chain / change |
| **Hetzner and Scaleway consoles** | Infrastructure, database, storage, backup status | Infra |

Pulse and Telescope write to **dedicated database connections** (`config/database.php`) so monitoring data is isolated from production application tables and can be pruned independently. Telescope and Pulse dashboards are access-restricted to authenticated staff and are not exposed publicly.

## 4. Log protection

Chatvia shall protect log integrity and confidentiality per the Data Classification & Handling Policy:

- **Integrity / immutability.** `agent_activity` is append-only at the application layer and surfaced read-only. Deployment and code-change history is immutable in Git and GitHub Actions. No routine operational workflow updates or deletes audit records.
- **Confidentiality in transit.** All log transport is over TLS; Postgres connections use `sslmode=require` in production (`DB_SSLMODE`).
- **Confidentiality at rest.** Logs are stored within the EU — Hetzner (Germany) for compute & database, Scaleway (France) for storage & backups. Session payloads are encrypted at rest (`SESSION_ENCRYPT=true`); application-level secrets use the AES-256 `APP_KEY`.
- **Access control.** Monitoring dashboards (Pulse, Telescope, Nightwatch) and the Flare project require authentication; the planned Filament admin panel enforces staff 2FA. Access follows least privilege under the Access Control Policy.
- **Backups.** Audit-bearing tables are included in the database backup scope (dump-based backups via spatie/laravel-backup → separate Scaleway bucket (planned)), with quarterly restore tests.

## 5. Retention

- **`agent_activity`** is retained on a defined window and pruned on a Horizon schedule via the `PruneAgentActivity` command; the window aligns with the customer's plan-tier data-retention setting (90 / 180 / 366 days, `config/billing.php`).
- **Telescope / Pulse** diagnostic data is short-lived (debugging horizon, typically ≤ 7 days) and pruned automatically on their dedicated connections.
- **Flare** error events follow the provider's configured retention.
- **GitHub** audit, CI, and security-alert history is retained for the life of the repository.
- Retention windows shall be no shorter than needed for incident investigation and no longer than necessary under GDPR data-minimisation. Prune jobs are scheduled, not manual.

## 6. Alerting & review responsibilities

**Real-time alerting.** Flare alerts on new errors and error-rate spikes; GitHub notifies on secret-scanning and Dependabot findings; CI failures on `composer audit` / `npm audit` block merges and notify the Founder. These route to the Founder for triage and, where a security event is suspected, into the Incident Response Plan.

**Periodic review.** The Founder (Security Lead) shall:

| Cadence | Review |
|---|---|
| **Daily (as alerted)** | Flare error alerts, GitHub security alerts, CI gate failures. |
| **Weekly** | Pulse/Nightwatch health trends; a sampled review of `agent_activity` for anomalous tool calls; Dependabot backlog. |
| **Monthly** | Authentication-failure and rate-limit-rejection trends; access/role-change review. |
| **Quarterly** | Backup restore test; verification that prune schedules and retention windows are operating. |

Reviews and material findings are recorded so the review is evidenced for audit. Any event indicating compromise is escalated immediately to the Incident Response Plan and reported to **info@chatvia.ai**; events involving personal data also engage the privacy path (**info@chatvia.ai**).

## 7. Exceptions

Exceptions to this policy require documented Founder approval, a stated compensating control, and a review date. They are logged in the risk register per the Risk Management Policy.

## 8. Mapping

| Control area | SOC 2 (Common Criteria) | ISO/IEC 27001:2022 Annex A |
|---|---|---|
| Event logging (auth, admin, tool-call audit) | CC7.2 | A.8.15 |
| Anomaly detection & monitoring tooling | CC7.2 | A.8.16 |
| Alerting, triage & escalation to IR | CC7.2, CC7.3 | A.8.16, A.5.25 |
| Log protection / integrity & immutability | CC6.1, CC7.2 | A.8.15 |
| Log access control | CC6.1, CC6.3 | A.8.15, A.8.3 |
| Retention & pruning | CC7.2 | A.8.15, A.8.10 |
| Clock / timestamp reliability | CC7.2 | A.8.17 |
| Review responsibilities & evidence | CC7.2, CC7.3 | A.8.16, A.5.36 |

> Template prepared 2026-06-09. Review with qualified legal/security counsel before relying on it externally.
