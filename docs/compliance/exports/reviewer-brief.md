# Brief for the reviewer — Chatvia Data Processing Agreement

**Prepared 9 June 2026 · for legal review**

## Who we are

**Akkadian** (CVR/VAT 35709738), Skovbrynet 50, 1 tv, Næstved, Denmark. We operate **Chatvia** (chatvia.ai), an embeddable AI chatbot / agent SaaS. We are a small, founder-led company.

## What we are asking you to do

Please review the attached **Data Processing Agreement (DPA)** — our Article 28 GDPR processor agreement that customers will sign — and confirm it is legally sufficient, enforceable, and balanced for Akkadian under **Danish and EU law**. Please flag any gaps and any terms that over-expose us. Track-changes are welcome.

## Our role and data flows

- Chatvia is a **data processor**; our customers (businesses that embed our chat widget on their websites) are the **controllers**.
- The widget collects personal data from the customer's website visitors: contact details ("Leads"), free-text conversation transcripts, uploaded knowledge-base documents, and vector embeddings derived from these.
- We do **not** use customer data to train AI models. We run open-weight models for inference only.
- Retention is plan-based (90 / 180 / 366 days), enforced by automated deletion jobs.

## Sub-processors (all EU except Stripe's US parent)

| Sub-processor | Role | Country |
|---|---|---|
| Hetzner | Compute, PostgreSQL database, Redis | Germany |
| Scaleway | AI inference, object storage, backups, email | France |
| Stripe | Billing & payments | Ireland (+ US parent — SCCs) |
| Bunny.net (BunnyCDN) | CDN / widget delivery | Slovenia |
| Plausible | Privacy-friendly analytics | Estonia |
| Flare | Error monitoring | Belgium |

All core processing stays within the EU/EEA.

## Please confirm or advise on

1. All eight **Art. 28(3)(a)–(h)** processor obligations are present and adequately drafted.
2. The **sub-processor** mechanism (general authorisation + 30-day advance notice + objection right) is enforceable.
3. The **international-transfer** wording (SCCs for the Stripe US leg) is correct and sufficient — and whether a transfer impact assessment is needed.
4. **Liability, indemnity, and audit rights** are balanced and do not over-expose a small company.
5. **Annex 2 (security measures)** and **Annex 3 (sub-processors)** describe our setup accurately and safely.
6. Consistency with our main **Terms of Service** (referred to as the "Principal Agreement").
7. **Governing law / forum** — currently Danish law; please confirm this is appropriate.
8. Whether we need an **Art. 27 EU representative** (we are established in Denmark, so likely not) and a **Data Protection Officer** (likely not).
9. The **48-hour breach-notification** commitment to customers — is this wording safe for us to give?

## Scope note

This is a one-off review of a single template document. We are an early-stage company and need a focused, practical pass — not a full compliance programme.

Contact: info@chatvia.ai
