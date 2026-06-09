# Information Security Policy

| | |
|---|---|
| **Owner** | Founder / Security Lead |
| **Version** | 1.0 |
| **Effective date** | 2026-06-09 |
| **Review cadence** | Annual (or on material change) |
| **Classification** | Internal |

## 1. Purpose

This is the master policy of Chatvia's Information Security Management System (ISMS). It states top management's commitment to information security, defines the scope of the ISMS, assigns responsibilities, and governs the sub-policies that detail individual controls.

Chatvia operates an embeddable AI chatbot/agent SaaS. A JavaScript widget runs on customer websites, holds conversations with end-user visitors, and captures contact details that become **Leads**. Chatvia therefore handles personal data of EU residents on behalf of its customers. In data-protection terms Chatvia is a **processor**; each customer workspace is the **controller**. Protecting the confidentiality, integrity, and availability of that data is core to the business and to customer trust.

Akkadian ("Chatvia") shall maintain, operate, and continually improve this ISMS in line with the SOC 2 Trust Services Criteria and ISO/IEC 27001:2022.

## 2. Scope

This policy applies to **all** Chatvia information assets, systems, and processing activities, including:

- **Application & infrastructure** — the Laravel 13 / PHP 8.4 monolith, Inertia + Vue 3 frontend, the embeddable widget, Postgres (pgvector + bm25), Redis, Reverb websockets, and Horizon queues.
- **Hosting** — production infrastructure in the EU: **Hetzner (Germany, EU)** for compute, the **PostgreSQL** database, Redis, Reverb websockets, and Horizon queues; **Scaleway (France, EU)** for AI inference, S3-compatible object storage for attachments and exports, database backups, and transactional email.
- **AI processing** — LLM inference performed by **Scaleway** using open-weight Qwen3 models; conversation content is transmitted to Scaleway for inference within the EU.
- **Data** — conversation transcripts, Leads (contact PII), uploaded knowledge-base documents, vector embeddings, API keys, and OAuth tokens.
- **Source & pipeline** — the GitHub repository, CI/CD workflows, and secrets.
- **People** — the founder and any contractors, and any future employees with access to Chatvia systems or data.

The ISMS covers the full data lifecycle: collection at the widget, transit, processing, storage, retention, and deletion.

## 3. Security commitment & objectives

Chatvia shall:

- Protect the **confidentiality, integrity, and availability** of customer and end-user data.
- Honour its **processor obligations** under the GDPR and customer Data Processing Agreements — process personal data only on documented controller instructions and apply data-protection-by-design.
- Keep production data resident in the **EU**.
- Apply **defence in depth** and **least privilege** across every system.
- Meet applicable legal, regulatory, and contractual obligations of Denmark.
- Continually improve the ISMS through monitoring, review, and remediation.

Measurable objectives are reviewed at the annual management review: zero unresolved critical vulnerabilities older than the SLA in the Vulnerability Management Policy; 100% of production changes through PR + green CI; and successful quarterly backup-restore tests.

## 4. Roles & responsibilities

Chatvia is effectively a **solo founder operation** (plus occasional contractors). Strict segregation of duties is not achievable; this section names the role-holder and, where a control would normally assume separation, the **documented compensating control**.

| Role | Held by | Responsibilities |
|---|---|---|
| **Top management / ISMS owner** | Founder | Owns the ISMS, approves policy, allocates resources, runs the annual management review, accepts residual risk. |
| **Security Lead** | Founder | Operates day-to-day security: access control, vulnerability triage, incident response, monitoring (Flare, Pulse, Telescope, Nightwatch). |
| **Engineering** | Founder (+ contractors) | Builds and deploys per the Change Management Policy. |
| **Data Protection contact** | Founder | Handles data-subject and controller requests; reachable at info@chatvia.ai. |
| **Contractors** | As engaged | Bound by confidentiality terms; access is least-privilege and time-bound; offboarded immediately on engagement end. |

**Compensating control for single-person segregation of duties.** No change reaches production from a local machine. Every change is a **GitHub Pull Request**, branch protection on `main` blocks direct pushes, and **CI must pass** (Pest, `composer audit`, `npm audit`, Pint, Larastan, ESLint — `.github/workflows/tests.yml`) before merge. Production deploys run only from `v*` tags and are gated on the test job (`.github/workflows/deploy.yml`). **Git history is immutable and attributable**, GitHub **secret scanning** is enabled, and the founder performs a documented **periodic self-review** of access, dependencies, and audit logs. The immutable tool-call audit (`agent_activity`, spatie/activitylog) and platform telemetry provide independent after-the-fact evidence.

## 5. Policy governance & exceptions

- **Authority.** This policy is approved by top management (the founder) and is mandatory for everyone with access to Chatvia systems.
- **Review.** Reviewed at least **annually** and on any **material change** (new processing activity, new sub-processor, significant architecture change, or a security incident).
- **Exceptions.** Any deviation must be **documented, risk-assessed, time-bound, and approved in writing** by the ISMS owner, then tracked to closure on the risk register. Undocumented deviations are non-compliant.
- **Non-compliance.** Violations may result in revoked access and, for contractors, termination of engagement.
- **Records.** Policies, approvals, exceptions, and review minutes are retained as ISMS records.

## 6. Sub-policy index

This master policy is implemented through the following sub-policies (each carrying the same header and review cadence):

| # | Sub-policy | Covers |
|---|---|---|
| 02 | **Access Control Policy** | Fortify + Sanctum auth, 2FA (TOTP + passkeys, `config/fortify.php`), `UserType` roles (Admin/Workspace/Employee/Chatter), per-resource Policies, TeamMembership, least privilege. |
| 03 | **Change Management Policy** | PR workflow, CI gates, `main` branch protection, tag-gated deploys. |
| 04 | **Vulnerability & Patch Management Policy** | Dependabot (`.github/dependabot.yml`), `composer`/`npm audit` in CI, planned annual third-party pentest of app + widget. |
| 05 | **Cryptography & Key Management Policy** | TLS in transit, Postgres `sslmode=require` (`DB_SSLMODE`), `SESSION_ENCRYPT=true`, `APP_KEY` AES-256, bcrypt hashing. |
| 06 | **Data Retention & Deletion Policy** | Scheduled prune jobs, retention windows in `config/billing.php` (90/180/366 days by plan), DataExport subsystem. |
| 07 | **Backup & Disaster Recovery Policy** | spatie/laravel-backup → separate Scaleway bucket, dump-based recovery (per backup schedule, to be finalised), quarterly restore test. |
| 08 | **Logging & Monitoring Policy** | Immutable `agent_activity` audit, Flare, Pulse, Telescope, Nightwatch. |
| 09 | **Incident Response Policy** | Detection, triage, containment, breach notification to controllers, info@chatvia.ai. |
| 10 | **Data Processing & Privacy Policy** | Processor obligations, sub-processors (Hetzner, Scaleway), EU residency, data-subject requests. |
| 11 | **Vendor / Sub-processor Management Policy** | Due diligence and oversight of Hetzner, Scaleway, and other sub-processors. |

## 7. Mapping

| Framework | Controls |
|---|---|
| **SOC 2 (Common Criteria)** | CC1.1 (integrity & ethical values), CC1.2 (board/management oversight), CC1.3 (structures, reporting lines, authorities — see Roles), CC1.4 (competence), CC1.5 (accountability); CC2.2–CC2.3 (internal & external communication of objectives); CC5.3 (policies deploy control activities). |
| **ISO/IEC 27001:2022** | Clause 5.1 (leadership & commitment), **Clause 5.2 (information security policy)**, Clause 5.3 (roles, responsibilities & authorities), Clause 6.1 (risk & exceptions), Clause 9.3 (management review). |
| **ISO/IEC 27001:2022 Annex A** | A.5.1 (policies for information security), A.5.2 (roles & responsibilities), A.5.4 (management responsibilities), A.5.19 (supplier relationships), A.5.36 (compliance with policies). |

---

> Template prepared 2026-06-09. Review with qualified legal/security counsel before relying on it externally.
