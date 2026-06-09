# Business Continuity & Disaster Recovery Policy
| | |
|---|---|
| **Owner** | Founder / Security Lead |
| **Version** | 1.0 |
| **Effective date** | 2026-06-09 |
| **Review cadence** | Annual (or on material change) |
| **Classification** | Internal |

## 1. Purpose

This policy defines how Akkadian ("Chatvia") preserves the availability and integrity of the Chatvia platform and the customer data it processes, and how Chatvia restores service after a disruptive event. Chatvia operates as a **data processor** on behalf of its customers (workspaces, the **controllers**); the data at risk includes conversation transcripts, captured contact records ("Leads"), uploaded knowledge-base documents, vector embeddings, API keys, and OAuth tokens. Restoring this data correctly — and provably — is a contractual and regulatory obligation, not just an operational one.

## 2. Scope

Applies to all production systems that store or process customer data:

- Postgres (pgvector + bm25) — primary datastore for transcripts, Leads, documents, embeddings, credentials.
- Redis — sessions, cache, and Horizon queue state.
- Scaleway S3-compatible object storage (EU) — attachments and DataExport artifacts.
- Application servers running Laravel 13 / PHP 8.4, Reverb websockets, and Horizon workers.
- The embeddable JS widget served to customer websites.
- Secrets, most critically the Laravel `APP_KEY` (AES-256), which decrypts application-level encrypted fields and session payloads.

All infrastructure is hosted in the **EU — Hetzner (Germany)** for compute, the **PostgreSQL** database, and Redis; **Scaleway (France)** for AI inference, object storage, backups, and email. LLM inference is performed by **Scaleway** (open-weight Qwen3 models); Hetzner and Scaleway availability are therefore external dependencies (Section 8).

## 3. Roles & sole-operator compensating control

Chatvia is operated by a solo founder with occasional contractors, so segregation of duties between "operator" and "recovery approver" cannot be enforced by headcount. The documented compensating control is: **all production changes, including infrastructure and recovery runbook changes, flow through GitHub PRs gated by CI (Pest, `composer audit`, `npm audit`, Pint, Larastan, ESLint) with `main` branch protection and an immutable Git history.** Recovery runbooks live in the repository under version control; restore tests (Section 7) are logged with date, operator, and outcome, giving a reviewable, after-the-fact audit trail in place of real-time dual control. The founder reviews this trail at each quarterly restore test.

## 4. Backups

Chatvia shall maintain layered, geographically and logically separated backups:

- **Dump-based database backups (planned).** The Hetzner-hosted PostgreSQL is recovered from periodic encrypted dumps via `spatie/laravel-backup`, not continuous transaction-log PITR. Dump-based backups are the primary recovery mechanism for the datastore.
- **Application-level backups (planned).** `spatie/laravel-backup`, scheduled on Horizon, shall produce encrypted database dumps and storage snapshots and ship them to a **separate Scaleway bucket** distinct from the production storage bucket. Separating the backup bucket from the primary bucket ensures a single bucket compromise, deletion, or misconfiguration does not destroy both live data and its backup.
- **Backup encryption & integrity.** Backup artifacts are encrypted (transit via TLS; at rest via storage-side and/or archive encryption). Backup jobs report success/failure to Flare; a missed or failed backup is an alertable monitoring event.
- **Retention alignment.** Backup retention shall meet or exceed the longest customer-facing data-retention window configured in `config/billing.php` (currently 90 / 180 / 366 days by plan), so a restore can always reconstitute data the customer is still entitled to.

> Backups containing customer PII inherit the same residency (EU) and processor obligations as production data. Backups are never copied outside the EU.

## 5. Recovery objectives (RTO / RPO)

| System / scenario | RTO (time to restore service) | RPO (max acceptable data loss) |
|---|---|---|
| Postgres data loss / corruption (dump restore) | **≤ 8 hours** | **per backup schedule (to be finalised)** (last completed dump) |
| Full application/region rebuild from backups | **≤ 24 hours** | **≤ 24 hours** (last completed `laravel-backup` run) |
| Object storage (attachments/exports) loss | **≤ 24 hours** | **≤ 24 hours** |
| Redis loss (sessions/queues) | **≤ 4 hours** | **Tolerated** — Redis holds transient state; jobs are idempotent or re-dispatchable |

These targets are commitments for Chatvia-controlled recovery actions. They are **conditional on Hetzner and Scaleway platform availability**; a region-wide provider outage is governed by Section 8.

## 6. APP_KEY off-box escrow

The Laravel `APP_KEY` decrypts application-encrypted fields (including stored credentials and OAuth tokens) and, with `SESSION_ENCRYPT=true`, session payloads. **A backup without the `APP_KEY` is undecryptable and worthless.** Therefore:

- The `APP_KEY` shall be escrowed **off-box** — stored outside the production hosts and outside the application repository — in a dedicated secrets manager and a sealed offline copy held by the founder.
- The escrow is **independent of any single Hetzner or Scaleway resource** so that loss of the production environment does not also lose the decryption key.
- Key rotation, if performed, must retain prior key versions for as long as any backup encrypted under the old key is retained, so historical backups remain recoverable.
- Access to the escrowed key is restricted to the founder and logged.

## 7. Restore testing

Backups that have never been restored are assumptions, not controls. Chatvia shall:

- Perform a **quarterly restore test**: provision a clean isolated environment, restore the latest `laravel-backup` artifact (the dump-based recovery path), apply the escrowed `APP_KEY`, and verify that encrypted fields, transcripts, and Leads decrypt and read back correctly.
- Record each test in the recovery log: date, operator, backup/restore point used, measured restore time vs. RTO target, decryption verification result, and any remediation.
- Treat a failed or out-of-target restore test as an incident and remediate before the next quarter.
- Re-test after any material change to the schema, encryption configuration, backup tooling, or `APP_KEY` handling.

## 8. Dependency on Hetzner & Scaleway (EU) availability

Chatvia's compute and the PostgreSQL database run on **Hetzner (EU, Germany)**; object storage, backups, and LLM inference run on **Scaleway (EU, France)**. Chatvia's own recovery cannot exceed its providers' platform availability.

- **Provider-concentration risk is accepted and documented.** Reliance on two EU providers is a known trade-off, chosen to keep all processing within the EU for data-residency reasons.
- **Mitigation.** Dump-based backups shipped to a separate Scaleway bucket reduce dependence on any single Hetzner or Scaleway resource. Where the providers offer multi-AZ or cross-region options, Chatvia shall prefer them for the datastore.
- **Degraded-inference handling.** If Scaleway LLM inference is unavailable, chat ingestion and data capture should fail gracefully and queue where safe; transcripts and Leads already persisted remain protected by the backup regime above.
- **External-outage communication.** During a Scaleway-attributable outage, Chatvia shall communicate status to affected customers via info@chatvia.ai / info@chatvia.ai as appropriate, including any RTO/RPO impact.

## 9. Invocation, communication & review

- **Invocation.** The founder declares a continuity/DR event when a Section 5 scenario causes, or is likely to cause, breach of an RTO/RPO target. Declaration starts the relevant runbook and the recovery log entry.
- **Customer & regulator notice.** Where an event involves loss of, or unauthorized access to, customer PII, the incident-response and breach-notification process is triggered in parallel (controllers notified without undue delay so they can meet their GDPR obligations). Privacy contact: info@chatvia.ai. Security contact: info@chatvia.ai.
- **Review.** This policy is reviewed annually or on material change (new datastore, provider, or encryption model). The quarterly restore tests are the primary evidence that the policy works in practice.

## 10. Mapping

| Framework | Controls |
|---|---|
| **SOC 2 (Common Criteria)** | CC7.5 (recovery from incidents), CC7.4 (incident response), CC9.1 (risk mitigation — backup/restore), CC6.1 / CC6.7 (`APP_KEY` escrow, encryption of backup data), A1.2 (availability — RTO/RPO, backups, environmental/provider dependency), A1.3 (recovery testing) |
| **ISO/IEC 27001:2022 Annex A** | A.5.29 (information security during disruption), A.5.30 (ICT readiness for business continuity), A.8.13 (information backup), A.8.14 (redundancy of processing facilities), A.5.22 / A.5.23 (supplier & cloud service management — Scaleway), A.8.24 (use of cryptography — `APP_KEY`/backup encryption) |

> Template prepared 2026-06-09. Review with qualified legal/security counsel before relying on it externally.
