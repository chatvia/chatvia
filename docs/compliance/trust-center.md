# Chatvia Trust Center

| | |
|---|---|
| **Owner** | Founder / Security Lead |
| **Version** | 1.0 |
| **Effective date** | 2026-06-09 |
| **Review cadence** | Annual (or on material change) |
| **Classification** | Internal |

## Our commitment

Chatvia powers AI chat for your website. That means visitor conversations, captured contacts, and your knowledge-base content pass through our platform — so earning your trust is part of the product, not an afterthought.

This page is a plain-language summary of how Chatvia protects data, where it lives, and how we support your compliance obligations. We keep it honest: we describe what is live today and clearly mark what is on our roadmap. If you need more detail than this page provides, the documents listed at the end are available on request.

Chatvia is operated by **Akkadian**, incorporated in **Denmark**, registered at **Skovbrynet 50, 1 tv, Næstved, Denmark**.

## Security

Security is built into how Chatvia is engineered and operated.

**Encryption**
- **In transit:** all traffic is served over TLS. Database connections are encrypted with TLS in production. We send HSTS headers so browsers stay on HTTPS.
- **At rest:** session payloads are encrypted, application-level secrets are encrypted with AES-256, and passwords are hashed with a strong one-way algorithm. Attachments and exports are stored in EU object storage.

**Authentication & access control**
- Multi-factor authentication is available to every account — TOTP authenticator apps and **passkeys** (WebAuthn).
- Access is least-privilege by design: every account carries a role, and access to each resource is checked against per-resource authorization policies and team-membership grants.
- Internal staff access to the admin surface will require enforced 2FA.

**Change management & secure development**
- All code changes ship through GitHub pull requests. Continuous integration runs the automated test suite, static analysis, linting, and dependency vulnerability scans on every push and pull request.
- Production deploys are gated on a green test run, and the main branch is protected with secret scanning enabled.
- As a small team, we operate documented compensating controls for separation of duties: every change is peer-reviewable, traceable to an immutable Git history, and subject to periodic self-review.

**Dependency & vulnerability management**
- Automated dependency updates run weekly across application and front-end packages.
- An independent third-party penetration test of the application and embeddable widget is planned and will be commissioned to support enterprise engagements.

**Monitoring**
- Application errors, performance, and runtime health are monitored continuously.
- Sensitive AI tool-call activity is recorded to an immutable audit log.

## Data privacy & GDPR

Chatvia is **GDPR-ready today**.

**Our role.** For visitor and contact data captured through the widget, Chatvia acts as a **data processor**. You — the customer operating the chatbot — are the **data controller**. We process personal data only on your documented instructions.

**Data Processing Agreement.** A GDPR-compliant **DPA is available and signable** as part of onboarding. It sets out roles, processing scope, security measures, sub-processor terms, and international-transfer safeguards.

**Data-subject rights.** Chatvia supports the rights of your end users — access, rectification, erasure, and portability. Conversation transcripts and captured contacts ("Leads") can be exported through our data-export tooling and deleted on request, so you can answer data-subject requests promptly.

**Retention & erasure.** Data is retained according to configurable retention windows tied to your plan (for example 90, 180, or 366 days), after which scheduled jobs automatically prune expired records. You can also request earlier deletion.

## Data residency

Chatvia is **hosted entirely in the EU**.

- Infrastructure runs in the EU — **Hetzner in Germany** for application servers and the database; **Scaleway in France** for AI inference, object storage, and backups.
- **AI inference** uses open-weight models served by Scaleway in the EU. Chat content is sent to Scaleway for inference; we do not use third-party AI providers outside the EU, and your data is not used to train foundation models.
- Attachments, exports, and backups are stored in Scaleway EU object storage.

Your data stays in Europe by default.

## Sub-processors

Chatvia uses a short, vetted list of sub-processors to deliver the service (hosting, AI inference, and storage are all provided within the EU). Our current sub-processor register is **available on request**, and the DPA includes notice terms for any changes.

## AI transparency

Chatvia is an AI product, and we believe people should know when they are talking to one.

- The widget is presented as an AI assistant — visitors are told they are interacting with automated AI, not a human agent.
- This aligns with the transparency obligations of the **EU AI Act (Article 50)**, which takes effect **2 August 2026**. We are building toward those obligations ahead of the deadline so your deployment stays compliant.

## Compliance status

We are deliberate about what we claim.

- **GDPR:** ready today — processor role, signable DPA, EU residency, data-subject-rights support, and configurable retention.
- **EU AI Act (Art. 50 transparency):** designed in, ahead of the 2 August 2026 effective date.
- **SOC 2 / ISO 27001:** on our roadmap. We operate against these control frameworks today and will make reports or certificates available on request once completed. We do **not** currently hold SOC 2 or ISO 27001 certification, and we will not claim one until it is earned.

## Documentation available on request (under NDA)

- **Data Processing Agreement (DPA)** — signable GDPR processor terms.
- **Sub-processor register** — current list with hosting, inference, and storage providers.
- **Security overview** — a deeper summary of our technical and organizational measures.

## Contact

- Privacy questions and data-subject requests: **info@chatvia.ai**
- Security reports and vulnerability disclosures: **info@chatvia.ai**

## Mapping

| Framework | Controls addressed |
|---|---|
| **SOC 2 (Common Criteria)** | CC6.1, CC6.6, CC6.7 (logical access, MFA, encryption in transit/at rest); CC6.8 (change authorization); CC7.1–CC7.2 (vulnerability & monitoring); CC8.1 (change management); CC1.4 / CC2.x (communication of commitments — this page); Privacy & Confidentiality criteria (data residency, retention, processor obligations) |
| **ISO/IEC 27001:2022 (Annex A)** | A.5.8 (security in project management), A.5.14 (information transfer), A.5.19–A.5.23 (supplier & cloud sub-processor management), A.5.34 (privacy & PII), A.8.5 (secure authentication / MFA), A.8.24 (cryptography), A.8.25–A.8.28 (secure development & change), A.8.8 (technical vulnerability management), A.8.15–A.8.16 (logging & monitoring) |
| **GDPR** | Art. 28 (processor / DPA), Art. 15–20 (data-subject rights), Art. 5(1)(e) & 17 (retention & erasure), Art. 32 (security of processing), Ch. V (EU residency / transfers) |
| **EU AI Act** | Art. 50 (transparency — disclosure of AI interaction) |

> Template prepared 2026-06-09. Review with qualified legal/security counsel before relying on it externally.
