# Data Processing Agreement (Art. 28 GDPR)
| | |
|---|---|
| **Owner** | Founder / Security Lead |
| **Version** | 1.0 |
| **Effective date** | 2026-06-09 |
| **Review cadence** | Annual (or on material change) |
| **Classification** | Internal |

This Data Processing Agreement (**"DPA"**) forms part of, and is incorporated into, the agreement for the provision of the Chatvia service (the **"Principal Agreement"**) between:

- **Akkadian** (CVR/VAT 35709738), registered in **Denmark** at **Skovbrynet 50, 1 tv, Næstved, Denmark**, operating the Chatvia service (**"Chatvia"**, the **"Processor"**); and
- the customer that subscribes to the Chatvia service (the **"Customer"**, the **"Controller"**).

This DPA governs the Processing of Personal Data by Chatvia on behalf of the Customer and gives effect to Article 28(3) of Regulation (EU) 2016/679 (**"GDPR"**). In the event of conflict between this DPA and the Principal Agreement on data protection matters, this DPA prevails.

## 1. Definitions

Terms not defined here carry the meaning given in the GDPR. **"Controller"**, **"Processor"**, **"Data Subject"**, **"Personal Data"**, **"Processing"**, **"Personal Data Breach"**, **"Sub-processor"**, and **"Supervisory Authority"** are as defined in Articles 4 and 28 GDPR. **"Applicable Data Protection Law"** means the GDPR, the UK GDPR, and any national implementing or successor legislation. **"Standard Contractual Clauses"** (**"SCCs"**) means the clauses adopted by Commission Implementing Decision (EU) 2021/914.

## 2. Roles and subject matter

2.1 In respect of Personal Data Processed under the Principal Agreement, the Customer acts as **Controller** and Chatvia acts as **Processor**. Where the Customer is itself a processor for a third-party controller, Chatvia acts as a sub-processor and the obligations in this DPA apply *mutatis mutandis*.

2.2 The **subject matter, duration, nature, purpose, categories of Data Subjects, and categories of Personal Data** are set out in **Annex 1**.

## 3. Article 28(3) processor obligations

Chatvia shall comply with each of the following.

### (a) Process only on documented instructions

3.1 Chatvia shall Process Personal Data **only on the Customer's documented instructions**, including with regard to international transfers, unless required to do so by Union or Member State law to which Chatvia is subject; in such a case Chatvia shall inform the Customer of that legal requirement before Processing, unless that law prohibits such information on important grounds of public interest.

3.2 The Customer's documented instructions are constituted by: (i) the Principal Agreement; (ii) this DPA and its Annexes; and (iii) the configuration choices the Customer makes in the Chatvia application (widget deployment, knowledge-base uploads, retention plan, connector/OAuth authorisations, channel integrations). The technical scope of Customer instructions is enforced through role-based access controls, such that each workspace's data is logically isolated from other customers.

3.3 Chatvia shall **not** Process Personal Data for its own purposes. Chatvia shall not use Customer Personal Data, conversation transcripts, or Leads to train, fine-tune, or improve any general-purpose model. LLM inference is performed on open-weight models hosted within the EU by the inference sub-processor identified in Annex 3; chat content sent for inference is used solely to generate the response and is not retained for model training.

3.4 If Chatvia believes an instruction infringes Applicable Data Protection Law, it shall promptly inform the Customer (without obligation to perform legal review of instructions).

### (b) Confidentiality of authorised persons

3.5 Chatvia shall ensure that persons authorised to Process Personal Data are bound by an obligation of confidentiality (contractual or statutory) and Process Personal Data only as instructed.

3.6 **Compensating control (small team).** Chatvia operates with a sole engineer (the Founder) and occasional contractors. Where segregation of duties is impractical, confidentiality is preserved through: written confidentiality terms in every contractor engagement; least-privilege production access; all production changes made through peer-reviewed, version-controlled releases with automated checks and an immutable change history; and periodic self-review of access and audit logs.

### (c) Security measures (Art. 32)

3.7 Chatvia shall implement the technical and organisational measures set out in **Annex 2**, designed to ensure a level of security appropriate to the risk, including the pseudonymisation and encryption of Personal Data, confidentiality, integrity, availability and resilience of systems, and regular testing of effectiveness.

### (d) Sub-processors

3.8 The Customer grants Chatvia a **general authorisation** to engage Sub-processors, subject to the conditions in this clause.

3.9 The current Sub-processors are listed in **Annex 3** and maintained in Chatvia's published sub-processor register.

3.10 Chatvia shall give the Customer **at least 30 days' advance notice** of any intended addition or replacement of a Sub-processor (by updating the register and notifying the Customer). The Customer may **object** on reasonable data-protection grounds within that period. If the parties cannot resolve the objection, the Customer may terminate the affected service for the portion that cannot be provided without the Sub-processor, as its sole remedy.

3.11 Chatvia shall impose on each Sub-processor, by written contract, **data-protection obligations equivalent to those in this DPA** (Art. 28(4)), and remains fully liable to the Customer for the Sub-processor's performance.

### (e) Assist with Data Subject rights

3.12 Taking into account the nature of the Processing, Chatvia shall assist the Customer by appropriate technical and organisational measures, insofar as possible, to fulfil the Customer's obligation to respond to requests to exercise Data Subject rights under Chapter III GDPR (access, rectification, erasure, restriction, portability, objection). Self-service tooling — data export (access / portability) and scheduled deletion (erasure) — enables the Customer to satisfy most requests directly; Chatvia provides additional assistance on request.

### (f) Assist with breach notification and DPIAs

3.13 Chatvia shall notify the Customer **without undue delay, and in any event within 48 hours**, after becoming aware of a Personal Data Breach affecting Customer Personal Data, providing the information the Customer reasonably needs to meet its Art. 33/34 obligations (nature of the breach, categories and approximate numbers affected, likely consequences, measures taken). Detection is supported by continuous application monitoring, error tracking, and an immutable activity audit trail.

3.14 Chatvia shall assist the Customer, taking into account the nature of Processing and information available, with **Art. 32 security obligations**, **Art. 33/34 breach notification**, and **Art. 35/36 Data Protection Impact Assessments and prior consultation**.

### (g) Deletion or return at end of contract

3.15 On termination or expiry of the Principal Agreement, at the Customer's choice, Chatvia shall **delete or return** all Personal Data and delete existing copies, unless Union or Member State law requires retention. Return is available via the data-export tooling. Deletion follows within **30 days** of termination, subject to the rolling backup-expiry window described in Annex 2; backups are cycled out on their normal schedule and not separately restored.

### (h) Audit rights and information

3.16 Chatvia shall make available to the Customer all information necessary to demonstrate compliance with Art. 28 and allow for and contribute to **audits**, including inspections, conducted by the Customer or an auditor it mandates.

3.17 Audits shall be conducted on reasonable prior notice (at least 30 days), no more than once per 12 months (save where required by a Supervisory Authority or following a Personal Data Breach), during business hours, subject to confidentiality, and in a manner that does not disrupt Chatvia's operations or compromise other customers' data. Chatvia may satisfy audit requests in the first instance by providing this DPA, Annex 2, the sub-processor register, and any available third-party assessment or penetration-test summary.

## 4. International transfers

4.1 All Customer Personal Data is stored and Processed within the **EU** — infrastructure and the database in **Germany**, and AI inference, object storage, and backups in **France** — by the sub-processors identified in **Annex 3**.

4.2 Chatvia shall not transfer Personal Data outside the EEA except on the Customer's documented instruction or to provide the service. For any unavoidable transfer to a third country without an adequacy decision, the parties shall rely on the **Standard Contractual Clauses (Module Two: Controller-to-Processor)**, which are incorporated by reference, together with any supplementary measures identified by a transfer impact assessment.

## 5. Liability and term

This DPA takes effect on the Effective Date and remains in force for the duration of the Principal Agreement and for as long as Chatvia Processes Personal Data on the Customer's behalf. Liability is governed by the limitations in the Principal Agreement, except where Applicable Data Protection Law prohibits such limitation.

---

## Annex 1 — Processing details

| Item | Detail |
|---|---|
| **Subject matter** | Provision of the Chatvia embeddable AI chatbot/agent SaaS. |
| **Duration** | The term of the Principal Agreement plus any deletion/return window (clause 3.15). |
| **Nature** | Collection, storage, structuring, retrieval, embedding (vectorisation), AI inference, transmission, and deletion of Personal Data, by automated means. |
| **Purpose** | To operate conversational AI agents on the Customer's website/channels, capture and manage Leads, and provide knowledge-base-grounded responses. |
| **Categories of Data Subjects** | The Customer's website visitors and end-users ("Visitors"); captured contacts ("Leads"); the Customer's own personnel/workspace users. |
| **Categories of Personal Data** | Identifiers and contact details (name, email, phone) submitted by Visitors/Leads; free-text **conversation transcripts** (which may contain any Personal Data the Visitor types); content of **uploaded knowledge-base documents**; **vector embeddings** derived from the above; technical metadata (IP-derived country, timestamps, session identifiers); workspace-user account data. |
| **Special-category data** | Not solicited. The Customer must not configure the service to collect Art. 9 data; any such data entered by a Visitor is Processed on the Customer's instruction and at the Customer's risk. |
| **Retention** | Per the Customer's plan-based retention window (e.g. 90 / 180 / 366 days), enforced by automated deletion. |

## Annex 2 — Technical and Organisational Measures (Art. 32)

**Encryption & pseudonymisation**
- All data is encrypted in transit using TLS, including connections to the database.
- Sensitive data is encrypted at rest using AES-256 — including session data, stored API keys, and access tokens.
- Passwords are stored using strong, salted one-way hashing.
- Vector embeddings are pseudonymised numerical representations rather than raw text.

**Access control & authentication**
- Multi-factor authentication is available (authenticator-app one-time codes and passkeys / WebAuthn). Programmatic access uses scoped API tokens.
- Role-based access control enforces least privilege, and each customer workspace's data is logically isolated from other customers.
- Staff and administrative access requires multi-factor authentication.

**Confidentiality, integrity & resilience**
- An immutable, append-only audit log records tool and agent activity.
- Continuous monitoring and error detection across the application.
- Web security headers (Content-Security-Policy, HSTS, X-Frame-Options, X-Content-Type-Options) and request rate limiting.

**Availability & backup**
- Encrypted backups are stored in a separate, geographically isolated location, hosted by a different provider from the primary database, with **periodic restore testing**. The recovery point is determined by the backup schedule.

**Secure development & vulnerability management**
- All code changes are peer-reviewed and version-controlled, with automated secret scanning.
- Automated tests, dependency-vulnerability scanning, and static analysis run on every change; production releases are gated on these checks passing.
- Dependencies are monitored and updated regularly for security patches.
- A third-party penetration test of the application and widget is engaged when required.

**Deletion**
- Automated processes enforce data retention and deletion; data can be exported on request; data is deleted on termination per clause 3.15.

## Annex 3 — Approved Sub-processors

The authoritative, current list is maintained in Chatvia's **published sub-processor register**; this Annex reflects it as at the Effective Date.

| Sub-processor | Service provided | Data Processed | Location |
|---|---|---|---|
| **Hetzner Online GmbH** | Cloud infrastructure hosting — compute and the database | All categories in Annex 1 | EU (Germany) |
| **Scaleway SAS** | AI inference (open-weight models), object storage (attachments, exports), database backups, and transactional email | All categories in Annex 1; chat content for inference | EU (France) |

Additions or replacements are governed by clause 3.8–3.11 (general authorisation, 30-day advance notice, objection right).

---

## Mapping

**SOC 2 Common Criteria:** CC1.4 (confidentiality commitments / personnel) · CC2.3 (communication with external parties) · CC6.1–CC6.3 (logical access, encryption, least privilege) · CC6.7 (transmission encryption) · CC7.2–CC7.4 (incident detection & response) · CC8.1 (change management) · CC9.2 (vendor/sub-processor management) · P-series Privacy criteria (collection, retention, disposal).

**ISO/IEC 27001:2022 Annex A:** A.5.10 (acceptable use) · A.5.12–A.5.14 (classification, handling, transfer) · A.5.19–A.5.22 (supplier relationships & monitoring) · A.5.23 (cloud security) · A.5.26/A.5.28 (incident response, evidence) · A.5.33–A.5.34 (records protection, privacy/PII) · A.8.3–A.8.5 (access restriction, authentication) · A.8.12 (data leakage / pseudonymisation) · A.8.24 (cryptography) · A.8.13 (backup) · A.8.25–A.8.29 (secure development, testing) · A.8.32 (change management).

**GDPR:** Art. 28(3)(a)–(h), Art. 28(4), Art. 32, Art. 33/34, Art. 35/36, Chapter V (Arts. 44–49, SCCs).

> Template prepared 2026-06-09. Review with qualified legal/security counsel before relying on it externally.
