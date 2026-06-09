# Records of Processing Activities (Processor — Art. 30(2) GDPR)
| | |
|---|---|
| **Owner** | Founder / Security Lead |
| **Version** | 1.0 |
| **Effective date** | 2026-06-09 |
| **Review cadence** | Annual (or on material change) |
| **Classification** | Internal |

## 1. Purpose and scope

This record documents the categories of personal-data processing that **Akkadian** ("Chatvia") carries out **on behalf of its customers** in operating the Chatvia embeddable AI chatbot/agent SaaS. It is maintained under **Article 30(2) GDPR** (records kept by a processor).

Chatvia acts as a **data processor**. Each Chatvia customer (a "workspace") is the **data controller** that determines the purposes and means of processing the personal data of its website visitors and staff. Chatvia processes that data solely on documented instructions from the controller, set out in the Data Processing Agreement (DPA) incorporated into the Chatvia Terms of Service.

### 1.1 Non-applicability of the <250-employee exemption

The Art. 30(5) exemption for organisations under 250 employees **does not apply**. The processing carried out by Chatvia:

- is **not occasional** — it is continuous and core to the service;
- is likely to result in a **risk to the rights and freedoms** of data subjects (large-scale capture of contact PII and IP addresses); and
- may include **special-category data** (Art. 9) because chat is free-text and a visitor may volunteer health, religious, political or other sensitive information that the agent then transmits to the LLM and stores in the transcript.

Chatvia therefore maintains this full record regardless of headcount.

## 2. Processor and controller identities

| Role | Party |
|---|---|
| **Processor** | Akkadian (CVR/VAT 35709738), Skovbrynet 50, 1 tv, Næstved, Denmark |
| **Processor representative / contacts** | info@chatvia.ai (data protection), info@chatvia.ai (security incidents) |
| **Controllers** | Each Chatvia customer (workspace). Identities are recorded in the `users` table (`UserType::Workspace`) and the executed DPA; a current list is exportable on request. |
| **Controller DPO / contact** | As designated by each controller in its account / DPA. |

A standalone **DPO is not mandatory** under Art. 37 for Chatvia at present; the Founder / Security Lead is the accountable point of contact (info@chatvia.ai). This assessment is revisited at each annual review.

## 3. Categories of processing carried out on behalf of controllers

Chatvia performs the following processing operations for every controller:

- **Collection & intake** — receiving visitor messages and form input through the embeddable JS widget.
- **Storage** — persisting conversation transcripts, captured contacts ("Leads"), and controller-uploaded knowledge-base documents (Postgres, EU).
- **AI inference** — transmitting chat content and retrieved context to the LLM for response generation.
- **Vector indexing / retrieval (RAG)** — generating embeddings from documents and messages and storing them for semantic search (pgvector + bm25).
- **Lead capture & enrichment** — extracting contact details volunteered in conversation into structured Lead records.
- **Notification & broadcast** — real-time delivery of messages to controller staff (Reverb websockets) and email/notification dispatch.
- **Analytics & usage metering** — aggregate conversation and token/usage statistics for the controller's dashboard and billing.
- **Export & deletion** — data export (DataExport subsystem) and scheduled / on-request erasure.

## 4. Categories of data subjects

- **Website visitors** of the controller (anonymous "Visitors" and identified end-users / "Chatters").
- **Leads** — visitors whose contact information has been captured.
- **Controller staff** — workspace owners, team members and employees who operate agents (`UserType::Workspace`, `Employee`; access via per-resource Policies + TeamMembership).

## 5. Categories of personal data

| Category | Examples | Notes |
|---|---|---|
| Identity & contact | name, email, phone, company | Captured into Lead records |
| Online identifiers | IP address, approximate geolocation, browser session identifiers | Visitor / widget session |
| Communication content | full chat transcripts, attachments, knowledge-base documents | Free-text — see special-category note |
| Authentication data | hashed passwords (bcrypt), 2FA TOTP secrets, passkeys, API keys, OAuth tokens | Controller-staff and integration credentials |
| Usage & technical metadata | timestamps, message/token counts, tool-call audit logs | |
| **Special-category data (Art. 9)** | any sensitive information a visitor freely volunteers in chat | Not deliberately solicited; cannot be excluded from free-text. Controllers are advised in the DPA to discourage solicitation of special-category data. |

## 6. Sub-processors and locations

Chatvia engages the following sub-processors. Controllers are notified of additions/changes per the DPA and may object.

| Sub-processor | Function | Location |
|---|---|---|
| **Hetzner Online GmbH** | Cloud hosting (compute), self-hosted **Postgres** (pgvector/bm25), Redis | **Germany (EU)** |
| **Scaleway** (LLM inference) | Open-weight **Qwen3** model inference — chat content sent for response generation | **France (EU)** |
| **Scaleway Object Storage** (S3-compatible) | Attachment / export / backup storage | **France (EU)** |
| Email / notification provider | Transactional email delivery | EU (per provider config) |
| Error & performance monitoring | Flare (telemetry may include incidental PII in error context) | Per provider region; PII minimisation applied |

> A current, dated sub-processor list is maintained at the Chatvia Trust / sub-processor page and is the authoritative version.

## 7. International transfers

Primary processing — hosting, database, LLM inference and object storage — occurs **entirely within the EU — Hetzner (Germany) for compute & database, Scaleway (France) for AI inference, object storage & backups**. No transfer of personal data to a third country occurs for the core service path.

Where any ancillary sub-processor (e.g. monitoring) processes data outside the EEA, transfers are covered by:

- an **EU adequacy decision**, where one exists; or
- the **EU Standard Contractual Clauses (SCCs)** plus a transfer impact assessment and supplementary technical measures (encryption in transit, data minimisation).

## 8. Retention periods

Retention is plan-configured in `config/billing.php` and enforced by scheduled prune jobs (Horizon-scheduled):

| Data | Retention window |
|---|---|
| Conversation transcripts & associated message data | **90 / 180 / 366 days** depending on the controller's plan tier |
| Leads | Retained while the controller's account is active, then per controller deletion instruction |
| Knowledge-base documents & embeddings | For the lifetime of the source / until the controller removes it |
| Tool-call audit log (`agent_activity`) | Retained for security/accountability; pruned per the same plan windows unless legally required longer |
| Backups | Rotated on the backup schedule (dump-based via spatie/laravel-backup to a separate Scaleway bucket) |

On controller instruction or account termination, Chatvia deletes or returns personal data per the DPA. The `DataExport` subsystem supports controller-driven export to satisfy data-subject access/portability requests.

## 9. Art. 32 technical & organisational measures (summary)

- **Encryption in transit** — TLS for all traffic; Postgres `sslmode=require` in production (`DB_SSLMODE`).
- **Encryption / protection at rest** — session payloads encrypted (`SESSION_ENCRYPT=true`); application-level encryption via Laravel `APP_KEY` (AES-256); passwords hashed with **bcrypt**.
- **Access control** — Laravel Fortify + Sanctum; 2FA via TOTP + passkeys (`config/fortify.php`, `settings/two-factor`); least-privilege via `UserType` roles, per-resource Policies and TeamMembership; staff 2FA to be enforced in the planned Filament admin panel.
- **Audit & monitoring** — immutable tool-call audit (`agent_activity`, spatie/activitylog); Flare, Laravel Pulse, Telescope and Nightwatch for observability.
- **Web hardening** — CSP / HSTS / X-Frame-Options / X-Content-Type-Options headers (`ContentSecurityPolicy` middleware); per-route rate limiting.
- **Secure development & change management** — all changes via GitHub PR; CI runs Pest + `composer audit` + `npm audit` + Pint + Larastan + ESLint (`.github/workflows/tests.yml`); production deploys gated on the test job and triggered on `v*` tags (`.github/workflows/deploy.yml`); `main` branch protection + secret scanning enabled.
- **Vulnerability management** — Dependabot weekly (composer/npm/actions, `.github/dependabot.yml`); third-party penetration test of app + widget planned when required by a deal.
- **Resilience & recovery** — dump-based backups via spatie/laravel-backup (planned) to a separate Scaleway bucket; recovery per backup schedule (to be finalised); quarterly restore test.

### 9.1 Organisational note (compensating control)

Chatvia operates with a sole engineer (the Founder) plus occasional contractors, so formal segregation of duties is not achievable. The **documented compensating control** is: every change flows through a pull request gated by CI, an immutable Git history provides an attributable change record, branch protection prevents direct pushes to `main`, and the Founder performs periodic self-review of access, dependencies and audit logs.

## 10. Mapping

| Framework | Controls |
|---|---|
| **GDPR** | Art. 30(2) (this record); Art. 28 (processor obligations / DPA); Art. 32 (security of processing); Art. 9 (special categories); Arts. 44–46 (transfers / SCCs); Art. 30(5) (exemption — assessed not to apply) |
| **SOC 2 (Common Criteria)** | CC1.x (governance), CC2.x (information & communication), CC6.1–CC6.8 (logical access, encryption), CC7.x (monitoring & incident response), CC8.1 (change management) |
| **ISO/IEC 27001:2022 Annex A** | A.5.34 (privacy & PII protection), A.5.31 (legal/contractual requirements), A.5.19–A.5.22 (supplier & cloud-service relationships), A.8.24 (cryptography), A.5.15–A.5.18 (access control), A.8.15–A.8.16 (logging & monitoring), A.8.32 (change management), A.5.30/A.8.13 (continuity & backup) |

> Template prepared 2026-06-09. Review with qualified legal/security counsel before relying on it externally.
