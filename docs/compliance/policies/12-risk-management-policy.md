# Risk Management Policy

| | |
|---|---|
| **Owner** | Founder / Security Lead |
| **Version** | 1.0 |
| **Effective date** | 2026-06-09 |
| **Review cadence** | Annual (or on material change) |
| **Classification** | Internal |

## 1. Purpose

This policy defines how Akkadian ("Chatvia") identifies, assesses, treats, and monitors information-security and privacy risks affecting the Chatvia platform — an embeddable AI chatbot SaaS that processes EU visitor PII (conversation transcripts, Leads, knowledge-base documents) on behalf of customer workspaces. Chatvia operates as a **data processor**; customers are **controllers**. This policy satisfies ISO/IEC 27001:2022 Clause 6.1 (actions to address risks and opportunities) and SOC 2 Common Criteria CC3.1–CC3.4.

## 2. Scope

All assets, systems, vendors, and processes that store or transmit customer or end-user data: the Laravel 13 / PHP 8.4 application, the embeddable JS widget, Postgres (pgvector + bm25), Redis, Reverb, Horizon, the EU infrastructure — Hetzner (Germany) for compute, database, and Redis, and Scaleway (France) for S3-compatible storage, backups, and email — and the Scaleway LLM inference path (open-weight Qwen3 models). It covers all personnel — the founder and any contractors.

## 3. Roles & Responsibilities

Chatvia is operated by a sole founder acting as **Risk Owner** and **Security Lead**. Where a control normally assumes segregation of duties, the documented **compensating control** is: a single accountable engineer; all production changes via GitHub PR + CI (`.github/workflows/tests.yml`) + branch protection on `main`; immutable Git history; and a scheduled quarterly self-review of the risk register. Contractors, when engaged, are granted least-privilege access and contribute changes only through the same PR/CI pipeline.

## 4. Risk Assessment Methodology

Chatvia uses a qualitative **likelihood × impact** model, re-assessed at least annually and on any material change (new subprocessor, new data category, architecture change, or post-incident).

### 4.1 Likelihood

| Score | Rating | Meaning |
|---|---|---|
| 1 | Rare | Not expected within 3+ years |
| 2 | Unlikely | Plausible but no known precedent |
| 3 | Possible | Could occur within a year |
| 4 | Likely | Expected within a year given exposure |
| 5 | Almost certain | Recurring or actively targeted |

### 4.2 Impact

Scored on the worst of confidentiality, integrity, availability, legal/regulatory (GDPR), and reputational dimensions.

| Score | Rating | Meaning |
|---|---|---|
| 1 | Negligible | No PII exposure; minutes of degradation |
| 2 | Minor | Internal-only; quick recovery |
| 3 | Moderate | Limited PII exposure; customer-visible outage |
| 4 | Major | Breach of EU end-user PII; reportable under GDPR Art. 33/34 |
| 5 | Severe | Mass PII breach, data loss, or extended outage; existential |

### 4.3 Risk score & tolerance

**Risk score = Likelihood × Impact** (1–25).

| Band | Score | Response |
|---|---|---|
| Low | 1–6 | Accept or monitor |
| Medium | 7–12 | Treat within 90 days |
| High | 13–19 | Treat within 30 days; documented interim mitigation |
| Critical | 20–25 | Immediate action; halt the affected change if needed |

**Risk appetite:** Chatvia has **low tolerance** for confidentiality risks to EU end-user PII and OAuth tokens, and **moderate** tolerance for short availability impacts. No High or Critical residual risk may be accepted without a written, time-boxed acceptance signed by the Risk Owner.

## 5. Risk Register

Chatvia maintains a **risk register** (`docs/compliance/risk-register.md`) as the system of record. Each entry records: ID, asset/threat, inherent likelihood × impact, existing controls, residual likelihood × impact, treatment decision, owner, due date, and review date. The register is reviewed quarterly (compensating self-review) and formally re-validated annually. Closed and accepted risks are retained for audit history; the register is version-controlled in Git.

## 6. Risk Treatment

For each risk the Risk Owner selects one option:

- **Mitigate** — apply or strengthen controls to reduce likelihood or impact (default for Medium+).
- **Transfer** — shift risk via contract or insurance (e.g. cyber-liability, DPA terms with subprocessors).
- **Avoid** — eliminate the activity or data flow (e.g. not storing a data category).
- **Accept** — formally accept residual risk within appetite, with documented justification and expiry.

Selected treatments become tracked actions with owners and due dates in the register.

## 7. Named Example Risks

The following are illustrative current register entries (scores reflect controls already in place).

| # | Risk | Inherent (L×I) | Key existing controls | Residual | Treatment |
|---|---|---|---|---|---|
| R1 | **Postgres data breach** (transcripts, Leads, embeddings, tokens) | 4×5=20 | Hetzner-hosted Postgres, EU isolation; `sslmode=require` in prod (`DB_SSLMODE`); `APP_KEY` AES-256 + `SESSION_ENCRYPT=true`; bcrypt; per-resource Policies + `UserType`/`TeamMembership` | 2×5=10 | Mitigate — add column-level encryption review for OAuth tokens; pursue pentest |
| R2 | **Widget XSS / injection** on customer sites | 3×4=12 | CSP/HSTS/X-Frame-Options/X-Content-Type-Options (`ContentSecurityPolicy` middleware); output escaping; per-route rate limiting | 2×4=8 | Mitigate — note `unsafe-inline`/no-nonce limitation; planned widget pentest |
| R3 | **Leaked secret / credential** (`.env`, API key, OAuth token) | 3×5=15 | GitHub secret scanning; `main` branch protection; secrets never in repo; Dependabot; immutable Git history | 2×5=10 | Mitigate — documented rotation runbook; alerting on secret-scan hits |
| R4 | **Prompt-injection KB exfiltration** (malicious content coerces agent to leak another tenant's data) | 3×4=12 | Per-agent KB scoping; tenant isolation in retrieval; immutable tool-call audit (`agent_activity`, activitylog); tool-approval checkpoints | 2×4=8 | Mitigate — strengthen retrieval tenant filters; monitor `agent_activity` for anomalies |
| R5 | **Vendor / subprocessor outage** (Hetzner compute/database, Scaleway storage, or Qwen3 inference) | 3×3=9 | Single-region EU dependency documented; Horizon retry/backoff; Flare/Pulse alerting; subprocessor list in DPA | 3×3=9 | Accept (monitor) — within availability appetite; revisit DR if SLA demands rise |
| R6 | **Ransomware / destructive compromise** of infrastructure | 2×5=10 | Dump-based backups via spatie/laravel-backup to a separate Scaleway bucket (planned), restored per backup schedule (to be finalised); quarterly restore test; least-privilege access | 2×4=8 | Mitigate — complete backup rollout; verify restore quarterly; offline/immutable backup copy |

## 8. Risk Acceptance

A residual risk may be **accepted** only when it falls within appetite (typically Low/Medium) and treatment is disproportionate to the exposure. Acceptance requires a register entry with: justification, accepting authority (Risk Owner), acceptance date, and a mandatory **re-evaluation date** (≤ 12 months). High and Critical risks may not be accepted as a steady state; any temporary acceptance pending a fix must carry a documented interim mitigation and a hard expiry.

## 9. Monitoring & Annual Review

- **Continuous:** Flare, Laravel Pulse, Telescope, and Nightwatch surface errors and anomalies; the immutable `agent_activity` audit and per-route rate limits provide tamper-evident operational signal.
- **Per change:** CI (Pest, `composer audit`, `npm audit`, Pint, Larastan, ESLint) gates every PR; production deploys are tag-gated on the test job. Dependabot raises weekly dependency risk for triage.
- **Quarterly:** self-review of the register, restore test of backups, and review of open treatments.
- **Annual:** full re-assessment of all register entries, methodology, and appetite; sign-off by the Risk Owner. Material changes (new subprocessor, data category, or architecture) trigger an immediate out-of-cycle review.

## 10. Exceptions

Any deviation from this policy must be recorded in the risk register as an accepted risk with the criteria in Section 8. Undocumented deviations are prohibited.

## Mapping

| Framework | Controls |
|---|---|
| **ISO/IEC 27001:2022** | Clause 6.1.1–6.1.3 (risk & opportunity actions, risk assessment, risk treatment); Clause 8.2–8.3 (operational risk assessment & treatment); Clause 9.3 (management review). Annex A: A.5.1, A.5.7 (threat intelligence), A.5.8 (security in project management), A.5.19–A.5.22 (supplier/ICT supply-chain risk), A.5.23 (cloud services), A.8.8 (technical vulnerability management). |
| **SOC 2 (TSC)** | CC3.1 (objectives & risk identification), CC3.2 (risk analysis — likelihood & impact), CC3.3 (fraud risk), CC3.4 (change-related risk); supported by CC4.1 (monitoring), CC7.1–CC7.2 (anomaly detection), CC9.1 (risk mitigation), CC9.2 (vendor & business-partner risk). |
| **GDPR** | Art. 32 (security of processing), Art. 28 (processor obligations), Art. 33/34 (breach notification thresholds reflected in impact scoring). |

> Template prepared 2026-06-09. Review with qualified legal/security counsel before relying on it externally.
