# Chatvia — Compliance Documentation

Operated by **Akkadian** (CVR/VAT 35709738), Skovbrynet 50, 1 tv, Næstved, Denmark.
Contact: `info@chatvia.ai` (data protection) · `info@chatvia.ai` (security).

This folder holds Chatvia's internal security policy set, GDPR processor artifacts, and the public Trust Center page. It is the evidence base referenced by the readiness roadmap in [`COMPLIANCE-PLAN.md`](COMPLIANCE-PLAN.md).

> **Status:** Version 1.0 templates, effective 2026-06-09. Tailored to Chatvia's actual stack but **not yet reviewed by legal counsel** — have the DPA in particular reviewed before relying on it externally.

## Internal policies (`policies/`)

SOC 2 (Common Criteria) + ISO 27001:2022 readiness set. Each policy maps to its CC / Annex A controls at the end.

| # | Policy |
|---|---|
| 01 | [Information Security Policy](policies/01-information-security-policy.md) |
| 02 | [Access Control Policy](policies/02-access-control-policy.md) |
| 03 | [Authentication & Password Policy](policies/03-authentication-password-policy.md) |
| 04 | [Change Management Policy](policies/04-change-management-policy.md) |
| 05 | [Data Classification & Handling Policy](policies/05-data-classification-handling-policy.md) |
| 06 | [Data Retention & Deletion Policy](policies/06-data-retention-deletion-policy.md) |
| 07 | [Cryptography & Encryption Policy](policies/07-cryptography-encryption-policy.md) |
| 08 | [Acceptable Use Policy](policies/08-acceptable-use-policy.md) |
| 09 | [Incident Response Plan](policies/09-incident-response-plan.md) |
| 10 | [Business Continuity & Disaster Recovery Policy](policies/10-business-continuity-dr-policy.md) |
| 11 | [Vendor & Sub-processor Management Policy](policies/11-vendor-subprocessor-management-policy.md) |
| 12 | [Risk Management Policy](policies/12-risk-management-policy.md) |
| 13 | [Human Resources Security Policy](policies/13-human-resources-security-policy.md) |
| 14 | [Vulnerability Management Policy](policies/14-vulnerability-management-policy.md) |
| 15 | [Logging & Monitoring Policy](policies/15-logging-monitoring-policy.md) |

## GDPR artifacts (`gdpr/`)

Chatvia is a **data processor**; its customers are **controllers**.

- [Data Processing Agreement (DPA)](../../docs/compliance/gdpr/data-processing-agreement.md) — the Art. 28 agreement to offer customers (publish at `/dpa`, accept at signup).
- [Sub-processor Register](../../docs/compliance/gdpr/subprocessors.md) — publish at `/subprocessors`; confirm the active vendor set before going live.
- [Records of Processing Activities (RoPA)](gdpr/records-of-processing-activities.md) — Art. 30(2) internal record.

## Trust Center (`trust-center.md`)

- [Trust Center](../../docs/compliance/trust-center.md) — public-facing security & privacy summary for chatvia.ai.

## Before publishing — checklist

- [ ] Legal review of the DPA (and a quick pass over the policy set).
- [ ] Confirm the **active** sub-processor list (Hetzner for compute & database, Scaleway for AI inference / storage / backups / email; verify Stripe / Flare / CDN before listing).
- [ ] Set `DB_SSLMODE=require` and `SESSION_ENCRYPT=true` in production (see `COMPLIANCE-PLAN.md`).
- [ ] Stand up `info@chatvia.ai` and `info@chatvia.ai` inboxes.
- [ ] Add the widget AI-disclosure label (EU AI Act Art. 50 — effective 2 Aug 2026).
- [ ] Assess whether an **Art. 27 EU representative** is needed (Akkadian is EU-established in Denmark, so likely **not** required — confirm).
