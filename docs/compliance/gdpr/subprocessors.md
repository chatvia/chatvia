# Sub-processor Register

| | |
|---|---|
| **Owner** | Founder / Security Lead |
| **Version** | 1.1 |
| **Effective date** | 2026-06-12 |
| **Review cadence** | Annual (or on material change) |
| **Classification** | Internal |

## 1. Purpose & scope

This register lists the third parties ("sub-processors") that Akkadian ("Chatvia") engages to process personal data on behalf of its customers in the course of operating the Chatvia service. It satisfies the transparency and prior-authorisation obligations of **GDPR Article 28(2)–(4)** and accompanies the customer-facing Data Processing Agreement (DPA).

**Controller / processor split.** Chatvia is a **processor** under **GDPR Article 4(8)**. Each customer workspace is the **controller** (Article 4(7)) of the personal data its embedded widget collects — conversation transcripts, captured contact details ("Leads"), and uploaded knowledge-base documents. Where a sub-processor receives that data, it acts as a **sub-processor** engaged by Chatvia under **Article 28(4)**, bound by data-protection terms no less protective than those Chatvia owes the controller.

This register covers only sub-processors on the **production data path** — parties that may access, store, or transmit customer personal data. It does **not** list every vendor configurable in the codebase. A workspace's optional integrations (e.g. its own MCP connectors or OAuth tools) are sub-processors of *that workspace's* data only when the workspace enables them, and are out of scope here.

## 2. Data residency — EU only

Chatvia's production sub-processor chain for **personal data** is established and operated within the **European Union**. Core compute and the database run on **Hetzner** in **Germany**; AI inference, object storage, **database backups**, and transactional email run on **Scaleway** in **France**. The remaining EU sub-processors are EU companies.

Two sub-processors have a non-EEA (US) nexus, both covered by the **EU Standard Contractual Clauses** (GDPR Chapter V):

- **Stripe** (US parent) for payment processing, under Stripe's Data Processing Agreement; and
- **Firecrawl** (US) for crawling **public websites only**. Firecrawl is used solely to fetch publicly accessible web pages a workspace chooses to add to its knowledge base. It receives only the public URL and returns the public page content — **no** conversation transcripts, Leads, end-user personal data, or private/login-protected content ever reach it. Because only public web content is sent, **no customer personal data leaves the EU through Firecrawl**.

**No training on inference data.** Chat content transmitted to Scaleway for inference is **not used to train Scaleway's or any third party's models**. Inference is transient: prompts and completions are processed only to return a response and are not retained for model improvement.

## 3. Sub-processors

### Hetzner Online GmbH — Germany (EU)

- **Purpose:** Primary infrastructure — compute servers, the database, and supporting services (cache, queues).
- **Data processed:** Live application data at rest — conversation transcripts, Leads (contact details), knowledge-base documents, vector embeddings, and encrypted application data.
- **Data Processing Agreement:** <https://www.hetzner.com/legal/data-processing>

### Scaleway SAS — France (EU)

- **Purpose:** AI inference (open-weight models); object storage of attachments and exports; database backups (in a separate bucket); transactional email.
- **Data processed:** Conversation content submitted for inference (may include visitor PII); knowledge-base attachments and exports; database backup archives; and email recipient address, name, and message content.
- **Data Processing Agreement:** <https://www.scaleway.com/en/contracts/>

### Stripe — Ireland (+ US parent, SCCs)

- **Purpose:** Subscription billing and payment processing.
- **Data processed:** Workspace billing identity, email, and card data (held by Stripe; Chatvia stores no card number — only Stripe customer/subscription identifiers).
- **Data Processing Agreement:** <https://stripe.com/legal/dpa>

### Bunny.net (BunnyWay d.o.o.) — Slovenia (EU); global edge

- **Purpose:** CDN / edge delivery of static assets and the embeddable widget script.
- **Data processed:** Visitor IP address, user-agent, and request metadata in transit.
- **Data Processing Agreement:** <https://bunny.net/dpa>

### Plausible Insights OÜ — Estonia (EU)

- **Purpose:** Privacy-friendly, cookieless website and product analytics.
- **Data processed:** Aggregated usage events. No cookies, no cross-site tracking, and no storage of directly identifying personal data by design.
- **Data Processing Agreement:** <https://plausible.io/dpa>

### Flare (flareapp.io) — Belgium (EU)

- **Purpose:** Application error and exception monitoring.
- **Data processed:** Diagnostic / exception data, which may incidentally include user identifiers or request context within stack traces.
- **Data Processing Agreement:** <https://flareapp.io/>

### Firecrawl (firecrawl.dev) — United States (SCCs)

- **Purpose:** Crawling and fetching **public web pages** when a workspace adds a public website as a knowledge-base source. Firecrawl retrieves the publicly accessible page and returns its text so it can be indexed for the agent.
- **Data processed:** Only the **public website address (URL)** the workspace chooses to crawl, and the **public page content** Firecrawl returns. Firecrawl receives **no** conversation transcripts, Leads, end-user personal data, account data, or private/login-protected content — it reads only pages that are already public on the open internet.
- **Transfers:** US-based; relies on the **EU Standard Contractual Clauses** (GDPR Chapter V). As only public web content is transmitted, no customer personal data is transferred.
- **Data Processing Agreement:** confirm current DPA/terms at <https://www.firecrawl.dev/>

DPA links are provided for reference; confirm the current link and terms with each provider at onboarding and at each annual review.

**Excluded — not sub-processors.** Internal monitoring tools that run **inside Chatvia's own infrastructure without sending customer personal data to a third party** are not sub-processors and are excluded from this register.

## 4. Sub-processor governance

Chatvia applies the following controls to every sub-processor under **Article 28**:

- **Written terms.** Each sub-processor is engaged under a DPA (or equivalent terms) imposing data-protection obligations no less protective than Chatvia's own, including confidentiality, security, and assistance duties (Art. 28(3)).
- **EEA-first selection.** Sub-processors are selected to keep processing within the EEA wherever feasible — Hetzner (Germany) and Scaleway (France) for all core hosting, storage, inference, and email. Where a sub-processor has a non-EEA nexus (Stripe for payments; Firecrawl for public-website crawling), the transfer relies on an adequacy decision or **EU Standard Contractual Clauses** (Art. 46). Firecrawl receives only public web content, never customer personal data.
- **Security diligence.** Sub-processors are assessed for technical and organisational measures appropriate to the data (Art. 32) before activation.
- **Solo-operator compensating control.** Chatvia is run by a sole founder/engineer. Sub-processor changes are made exclusively through version-controlled configuration (PR + CI + immutable Git history), and the active set is re-validated at each annual review and at every material change. This documented review substitutes for segregation-of-duties.

## 5. Change notification & objection (Article 28(2))

Chatvia maintains this register as the canonical, published sub-processor list and commits to:

- **30 days' advance notice** of any intended addition or replacement of a sub-processor that will process customer personal data, via this document and/or the customer notification channel on record.
- A **right to object** during that notice window. A controller may object on reasonable, data-protection-related grounds by writing to **info@chatvia.ai**. Chatvia will work in good faith to address the objection; where it cannot, the controller may terminate the affected service as set out in the DPA.
- **Emergency changes.** If a sub-processor must be replaced sooner to address a security or availability risk, Chatvia will notify affected controllers without undue delay and document the justification.

**Contact.** Sub-processor enquiries, objections, and DPA requests: **info@chatvia.ai**. Security matters: **info@chatvia.ai**. Registered operator: Akkadian, Skovbrynet 50, 1 tv, Næstved, Denmark, governed by the laws of Denmark.

## 6. Framework mapping

- **GDPR:** Art. 4(7)–(8) (controller / processor); Art. 28(2)–(4) (sub-processor authorisation, notice, objection, flow-down terms); Art. 32 (security of processing); Art. 46 (SCCs for non-EEA transfers); Chapter V (transfers).
- **SOC 2 (Common Criteria):** CC1.4 (accountability for outsourced functions); CC3.2 (risk identification, including vendors); CC9.2 (vendor & business-partner risk management); CC6.1 (logical access to data via vendors); CC6.7 (transmission of data to third parties).
- **ISO 27001:2022 Annex A:** A.5.19–A.5.23 (supplier relationships, agreements, supply-chain security, monitoring, and cloud-service security); A.5.14 (information transfer).

> Template prepared 2026-06-09. Review with qualified legal/security counsel before relying on it externally.
