# Vendor & Sub-processor Management Policy
| | |
|---|---|
| **Owner** | Founder / Security Lead |
| **Version** | 1.0 |
| **Effective date** | 2026-06-09 |
| **Review cadence** | Annual (or on material change) |
| **Classification** | Internal |

## 1. Purpose

Chatvia, operated by Akkadian ("Chatvia"), processes personal data on behalf of its customers. As an embeddable AI chatbot SaaS, Chatvia's widget collects EU visitor PII and converts captured contacts into Leads. Chatvia acts as a **data processor**; its customers (workspaces) are **controllers**. Chatvia depends on third parties — including LLM inference, hosting, and storage providers — that may process customer or visitor data on Chatvia's behalf. These are **sub-processors**.

This policy governs how Chatvia selects, onboards, contracts with, monitors, and offboards vendors and sub-processors, so that customer trust commitments, GDPR Article 28 obligations, and Chatvia's own security posture are preserved down the supply chain.

## 2. Scope

This policy applies to:

- **Sub-processors** — any third party that processes personal data (conversation transcripts, Leads, knowledge-base documents, embeddings, API keys, OAuth tokens) on Chatvia's behalf. Current sub-processors include **Hetzner** (EU hosting — compute, the PostgreSQL database, and Redis, in Germany) and **Scaleway** (EU — S3-compatible object storage, database backups, Qwen3 LLM inference, and transactional email, in France), and any payment, error-monitoring, or support providers.
- **Critical vendors** — third parties whose failure would materially affect Chatvia's confidentiality, integrity, or availability even if they do not process personal data (e.g., GitHub, Stripe, Flare, DNS/CDN, domain registrar).

It applies to all engagements regardless of contract value, including free-tier and trial usage of a service in production.

## 3. Roles & Responsibilities

Chatvia operates as a solo founder with occasional contractors. Where this policy implies segregation of duties, the **documented compensating control** applies: the sole engineer performs onboarding, review, and approval; every configuration change reaches production only via PR + CI + immutable Git history, and decisions are recorded in the sub-processor register and reviewed at the periodic review (Section 7). The Founder / Security Lead owns all vendor decisions, the register, and customer notifications.

## 4. Onboarding & Due Diligence

No third party may process personal data on Chatvia's behalf, and no critical vendor may be introduced into production, until the following due diligence is complete and recorded:

- **Data-flow assessment** — identify what data the vendor receives, the lawful basis, the data residency, and whether the vendor is itself a sub-processor or merely a tool.
- **Residency check** — sub-processors processing EU visitor or customer PII must keep data **in the EU** or under an equivalent transfer mechanism. Chatvia's default posture is EU-only — Hetzner (Germany) for compute & database, Scaleway (France) for AI inference, storage & backups. Any non-EU processing requires Standard Contractual Clauses (SCCs) or an adequacy decision and explicit recording of the transfer basis.
- **Security posture review** — request and retain the vendor's SOC 2 Type II report, ISO 27001:2022 certificate, security whitepaper, or equivalent. Where none exists, document a risk-based justification and compensating controls.
- **AI / model-training review** — for any LLM, embedding, reranking, or AI-inference vendor, confirm in writing that **data submitted for inference is not used to train, fine-tune, or improve the vendor's models**, and is not retained beyond the inference request except as needed to deliver the service. This is a hard gate; a vendor that cannot give this commitment is not approved for inference of customer content.
- **Sub-sub-processor disclosure** — confirm whether the vendor uses its own sub-processors and that flow-down DPA obligations apply.

Due-diligence artefacts are retained for the life of the engagement plus one year.

## 5. Contracting — DPAs

- A **signed Data Processing Agreement (DPA)** meeting GDPR Article 28 is required with every sub-processor before production processing begins.
- Each DPA shall bind the sub-processor to: process only on documented instructions; maintain appropriate technical and organisational measures; flow down equivalent obligations to its own sub-processors; assist Chatvia with data-subject requests and breach notification; and delete or return data on termination.
- DPAs covering EU PII transferred outside the EEA shall incorporate the current **SCCs**.
- LLM-inference DPAs shall additionally codify the **no-training / no-model-improvement** commitment from Section 4 as a contractual term, not merely a documentation note.
- Chatvia maintains its own customer-facing DPA mirroring these obligations upstream, so the controller→processor→sub-processor chain is contractually consistent.

## 6. Sub-processor Register

Chatvia shall maintain a single authoritative **sub-processor register**, version-controlled in the repository alongside this policy. Each entry records:

- Legal name and corporate location of the sub-processor.
- Service provided and the categories of personal data processed.
- Processing location / data residency.
- Transfer mechanism (EU-only, SCCs, adequacy).
- DPA status, signature date, and link.
- AI-training commitment status (where applicable).
- Date added and last-reviewed date.

A customer-facing version of the register is published (e.g., at a public sub-processors URL) so controllers can subscribe to changes. Material discrepancies between the internal and published registers are treated as a finding.

## 7. Advance Notice of New or Changed Sub-processors

- Chatvia shall give customers **at least 30 days' advance notice** before authorising a new sub-processor or materially expanding an existing one's processing (new data categories, new region).
- Notice is delivered via the published register, in-app notification, and/or email to the workspace admin contact, per the customer DPA.
- Customers retain the contractual right to object on reasonable data-protection grounds during the notice window. Chatvia shall work in good faith to resolve a documented objection, including offering an alternative or, failing that, treating it as a termination event under the customer agreement.
- The notice clock starts only after the new sub-processor's DPA is signed and its register entry is prepared.

## 8. Periodic Review & Monitoring

- The full sub-processor register and all critical-vendor relationships are reviewed **at least annually**, and on any material change (new data category, region change, ownership change, reported incident).
- At each review Chatvia shall reconfirm: DPA currency, residency, the no-training commitment, the vendor's current security attestation, and whether the relationship is still required.
- **Continuous technical monitoring** of the supply chain is automated where possible: Dependabot raises weekly composer/npm/GitHub-Actions updates (`.github/dependabot.yml`); CI runs `composer audit` and `npm audit` on every push/PR (`.github/workflows/tests.yml`); GitHub secret scanning and `main` branch protection guard the change pipeline. Application and integration failures surface through Flare, Laravel Pulse, Telescope, and Nightwatch.
- Vendor-side incidents or breaches trigger Chatvia's incident-response process and, where personal data is affected, the controller-notification obligations in the customer DPA.

## 9. Offboarding

On termination of a vendor relationship Chatvia shall: revoke credentials, API keys, and OAuth tokens; obtain written confirmation of data deletion or return per the DPA; remove the entry from the active register (retaining a dated historical record); and verify no residual data flows remain in configuration (`config/connectors.php`, `config/database.php`, environment secrets).

## 10. Exceptions

Any deviation from this policy (e.g., emergency adoption of a vendor before full due diligence) shall be documented with a justification, a remediation deadline, and the Founder / Security Lead's approval recorded in the register. Exceptions are reviewed at the next periodic review.

## 11. Mapping

| Framework | Controls |
|---|---|
| **SOC 2 (Common Criteria)** | CC2.3 (communication with external parties / sub-processor notice), CC3.2 (risk identification incl. vendors), CC9.2 (vendor & business-partner risk management), CC1.3 / CC1.5 (responsibility & accountability under the solo-founder compensating control) |
| **ISO/IEC 27001:2022 Annex A** | A.5.19 (information security in supplier relationships), A.5.20 (addressing security within supplier agreements / DPAs), A.5.21 (managing ICT supply-chain security), A.5.22 (monitoring, review & change management of supplier services), A.5.23 (information security for use of cloud services), A.5.14 (information transfer / data residency & SCCs) |
| **GDPR** | Art. 28 (processor & sub-processor obligations, written authorisation, flow-down), Art. 28(2)/(4) (general written authorisation + advance notice), Art. 44–46 (international transfers / SCCs) |

> Template prepared 2026-06-09. Review with qualified legal/security counsel before relying on it externally.
