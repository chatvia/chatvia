# Incident Response Plan

| | |
|---|---|
| **Owner** | Founder / Security Lead |
| **Version** | 1.0 |
| **Effective date** | 2026-06-09 |
| **Review cadence** | Annual (or on material change) |
| **Classification** | Internal |

## 1. Purpose & scope

This plan defines how Akkadian ("Chatvia") detects, responds to, and learns from security and privacy incidents affecting the Chatvia platform — the embeddable AI chatbot/agent SaaS, its EU infrastructure (Hetzner, Germany for compute & database; Scaleway, France for AI inference, object storage, backups & email), and the personal data it processes on behalf of customers.

Chatvia acts as a **data processor**; its customers (workspaces) are **controllers**. The platform stores conversation transcripts, captured contact PII ("Leads"), uploaded knowledge-base documents, vector embeddings, API keys, and OAuth tokens. A compromise of any of these is in scope.

This plan applies to all incidents touching production systems, customer data, source code, CI/CD, third-party processors (Hetzner compute & PostgreSQL database, Scaleway S3-compatible storage, Scaleway-hosted Qwen3 LLM inference), and the embeddable widget delivered to EU visitors.

**Team reality.** Chatvia is operated by a sole founder with occasional contractors. Where this plan assigns distinct roles, one person may hold several. The compensating control for the lack of segregation of duties is: all production change flows through GitHub PRs gated by CI (Pest, `composer audit`, `npm audit`, Pint, Larastan, ESLint), `main` branch protection, secret scanning, tag-gated deploys, and an immutable Git history that provides an after-the-fact audit trail of every change.

## 2. Roles

| Role | Default holder | Responsibilities |
|---|---|---|
| **Incident Commander (IC)** | Founder | Declares the incident, owns the timeline, makes containment/recovery calls, decides on breach notification. |
| **Technical Lead** | Founder / contractor | Investigates, contains, eradicates, recovers. Operates the stack (Laravel, Postgres, Redis, Horizon, the Hetzner and Scaleway consoles). |
| **Privacy Lead** | Founder | Runs the GDPR breach assessment, drafts controller notifications, maintains the breach register. |
| **Comms** | Founder | Customer-facing and (if required) public communication. |

External support is engaged as needed: qualified legal counsel for breach assessment, and the planned third-party pentest firm for forensic assistance.

## 3. Incident lifecycle

### 3.1 Detect

Signals are gathered from:

- **Flare** — application exceptions and error spikes.
- **Laravel Pulse / Telescope / Nightwatch** — runtime health, slow queries, anomalous request volume.
- **`agent_activity` immutable audit log** (spatie/activitylog) — every tool call is recorded; unexpected tool invocations or volume are an indicator.
- **GitHub secret scanning + Dependabot alerts** — leaked credentials, vulnerable dependencies.
- **CI failures** on `composer audit` / `npm audit` — a newly disclosed CVE in a shipped dependency.
- **Hetzner and Scaleway console alerts** — infrastructure, database, storage, and inference events.
- **External reports** to **info@chatvia.ai** — researchers, customers, or processors.

Anyone aware of a potential incident reports it to **info@chatvia.ai** without delay.

### 3.2 Triage & classify

The IC confirms whether the signal is a genuine incident, opens a dated incident record (timeline, evidence, decisions), and assigns a **severity** (§4). The IC explicitly determines whether **personal data is involved** — if yes, the GDPR path (§5) starts in parallel immediately, because the 72-hour clock for controllers is short and Chatvia's customers depend on Chatvia's prompt notice.

### 3.3 Contain

Stop the bleeding before fixing the root cause. Containment actions, selected by incident type:

- Rotate compromised secrets — `APP_KEY`, database credentials, Scaleway API keys, OAuth tokens, Sanctum tokens.
- Revoke affected sessions and API keys; force re-authentication.
- Apply per-route rate limiting / temporary blocks, or take the affected component offline.
- Disable a compromised connector, integration, or contractor access.
- Pause Horizon queues if a job pipeline is implicated.
- Snapshot evidence (logs, `agent_activity`, DB state) **before** mutating systems.

### 3.4 Eradicate

Remove the root cause: patch the vulnerable code or dependency via PR (so the fix inherits CI gating and Git provenance), remove malware/backdoors, close the misconfiguration, and confirm no persistence remains. Dependency fixes ship through Dependabot/manual bump and merge only after `composer audit` / `npm audit` pass.

### 3.5 Recover

Restore normal operations and verify integrity:

- Deploy the fix via a `v*` tag — production deploys are gated on the passing test job.
- Restore data from backups where needed (dump-based backups via spatie/laravel-backup → separate Scaleway bucket; recovery point per backup schedule, to be finalised), validating the restore against the quarterly restore-test procedure.
- Re-enable disabled components and confirm monitoring is green (Flare, Pulse).
- Watch for recurrence over a defined observation window before closing.

### 3.6 Post-mortem

Within **5 business days** of closure, the IC writes a blameless post-mortem covering: timeline, root cause, impact, what worked, what failed, and dated corrective actions with owners. Corrective actions that are code or config changes are tracked to merge. The post-mortem is filed with the incident record and reviewed at the next plan review.

## 4. Severity levels

| Severity | Definition | Target IC response time |
|---|---|---|
| **SEV-1 — Critical** | Confirmed breach of personal data; active intrusion; full outage; key/credential compromise with data exposure. | Immediate (≤ 1 hour) |
| **SEV-2 — High** | Likely incident with limited/contained data exposure; partial outage; exploited vulnerability without confirmed exfiltration. | ≤ 4 hours |
| **SEV-3 — Medium** | Security-relevant event, no confirmed data exposure (e.g. failed intrusion, internal misconfiguration). | ≤ 1 business day |
| **SEV-4 — Low** | Minor/contained issue, informational finding, near-miss. | Next business day |

Any incident involving personal data is **at least SEV-2** and triggers §5 regardless of technical severity.

## 5. GDPR personal-data-breach path

A **personal data breach** is a breach of security leading to accidental or unlawful destruction, loss, alteration, unauthorised disclosure of, or access to, personal data processed by Chatvia (transcripts, Leads, knowledge-base documents).

As a **processor**, Chatvia's primary obligation under **GDPR Art. 33(2)** is to notify affected **controllers (customers) without undue delay** after becoming aware of a breach, so they can meet their own **Art. 33(1) 72-hour regulator deadline**.

**Process:**

1. **Assess (Privacy Lead, started in parallel at triage):** confirm whether personal data is involved, which workspaces/data subjects, what categories, and likely consequences. Use `agent_activity`, application logs, and DB state as evidence.
2. **Notify affected customers without undue delay** — and in any case as fast as practicable, targeting **within 24 hours of confirmation** — via their account contact and **info@chatvia.ai**. Provide: nature of the breach, categories and approximate number of data subjects/records, likely consequences, measures taken/proposed, and a Chatvia contact point. Send a preliminary notice if full details are not yet known; follow up in phases.
3. **Support the controller** in any regulator (supervisory authority) or data-subject notification they must make.
4. **Record** the breach in the register (§6) whether or not external notification is required.

Decision to notify, and all notification content, is made by the IC with the Privacy Lead and, for any non-trivial breach, qualified legal counsel.

## 6. Breach register (Art. 33(5))

Chatvia maintains a **breach register** documenting **all** personal-data breaches — including those not externally notified — to satisfy **GDPR Art. 33(5)** accountability. Each entry records:

- Incident ID, dates (occurred / detected / contained / closed).
- Facts of the breach, categories and approximate volume of data subjects and records.
- Effects and consequences.
- Remedial action taken.
- Notification decisions (which controllers notified, when; whether the customer/regulator was informed) and the reasoning.

The register is retained for a minimum of **3 years**, stored in the internal compliance documentation set, and reviewed at each annual plan review.

## 7. Contact channels

- **Security incidents / disclosures:** info@chatvia.ai
- **Privacy / data-breach matters:** info@chatvia.ai
- **Registered entity:** Akkadian, Skovbrynet 50, 1 tv, Næstved, Denmark, governed by the laws of Denmark.

External security reports are acknowledged within **2 business days**.

## 8. Testing & maintenance

- This plan is reviewed at least **annually** and after any material change or SEV-1/SEV-2 incident.
- A **tabletop exercise** (walk-through of a simulated personal-data breach) is run at least annually.
- The backup restore test is run **quarterly**; a failed restore is itself a SEV-2 incident.

## Mapping

| Control area | SOC 2 (Common Criteria) | ISO/IEC 27001:2022 Annex A |
|---|---|---|
| Incident response process & lifecycle | CC7.3, CC7.4, CC7.5 | A.5.24, A.5.26 |
| Detection & monitoring | CC7.1, CC7.2 | A.8.15, A.8.16 |
| Incident reporting & escalation | CC2.2, CC2.3 | A.6.8, A.5.5 |
| Evidence collection & event logging | CC7.3 | A.8.15, A.5.28 |
| Breach assessment & notification (GDPR Art. 33) | CC7.4, P6.x (Privacy) | A.5.34, A.5.26 |
| Breach register / accountability | CC7.5 | A.5.34, A.5.36 |
| Post-incident learning & corrective action | CC7.5, CC4.1 | A.5.27 |
| Backup & recovery | A1.2, CC7.5 | A.8.13, A.5.30 |
| Change-controlled remediation | CC8.1 | A.8.32 |

> Template prepared 2026-06-09. Review with qualified legal/security counsel before relying on it externally.
