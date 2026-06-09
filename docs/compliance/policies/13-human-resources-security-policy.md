# Human Resources Security Policy
| | |
|---|---|
| **Owner** | Founder / Security Lead |
| **Version** | 1.0 |
| **Effective date** | 2026-06-09 |
| **Review cadence** | Annual (or on material change) |
| **Classification** | Internal |

## 1. Purpose

This policy defines how Akkadian ("Chatvia") manages personnel-security risk across the employment and engagement lifecycle. Because Chatvia operates as a **data processor** handling EU visitor PII (conversation transcripts, captured **Leads**, uploaded knowledge-base documents) on behalf of its customer **workspaces** (the controllers), every person with access to production systems is a control point. This policy ensures that anyone who can reach customer data is screened where lawful, bound by confidentiality, trained, and granted and revoked access in a timely, auditable way.

## 2. Scope

Applies to all **workers**: the founder, employees (`UserType::Employee`/`Admin`), and any contractor or vendor with logical or physical access to Chatvia source code, production infrastructure (the EU — Hetzner, Germany for compute & database; Scaleway, France for AI inference, object storage, backups & email), customer data, or internal tooling (GitHub, Flare, the Hetzner and Scaleway consoles, Horizon, Pulse, Telescope).

**Operating reality.** Chatvia is run by a **solo founder** with occasional contractors. Where a control assumes segregation of duties between the requester, approver, and implementer of access, the **documented compensating control** applies: the sole engineer makes every change through a GitHub Pull Request gated by CI (`.github/workflows/tests.yml` — Pest, `composer audit`, `npm audit`, Pint, Larastan, ESLint), protected by `main` branch protection and an immutable Git history, with a periodic self-review recorded against this policy. Production deploys occur only on `v*` tags gated on the passing test job (`.github/workflows/deploy.yml`).

## 3. Roles and Responsibilities

- **Founder / Security Lead** — owns this policy; approves all access grants and revocations; performs onboarding/offboarding; conducts the periodic self-review.
- **Workers** — read, acknowledge, and comply with this policy, the Acceptable Use Policy, and signed confidentiality terms.

## 4. Before Engagement

### 4.1 Background screening (where lawful)

- Chatvia **shall** conduct pre-engagement screening proportionate to the access a role grants and **only to the extent permitted by Denmark employment and data-protection law**. Screening is consent-based, documented, and limited to what is necessary.
- For any role with production-data or production-infrastructure access, screening **shall** include identity verification and confirmation of right to work; reference and/or qualification checks are performed where lawful and relevant.
- Contractors are screened or covered by an equivalent contractual attestation from their employer before access is provisioned.
- Screening records are retained as **Confidential** and accessible only to the Founder.

### 4.2 Confidentiality and terms

- Every worker **shall** sign a **Non-Disclosure / Confidentiality Agreement** before any access to source code or customer data. Confidentiality obligations expressly survive termination.
- Contractor engagements **shall** include written terms covering confidentiality, data-protection obligations consistent with Chatvia's processor commitments (GDPR Art. 28 sub-processor flow-down), IP assignment, and secure return/deletion of data on termination.
- Employment/contract terms reference this policy and the worker's security responsibilities.

## 5. During Engagement

### 5.1 Security-awareness training

- Workers **shall** complete security-awareness training **at hire (before production access)** and **at least annually** thereafter. Completion is recorded with the date and worker name.
- Training covers, at minimum: phishing and social engineering; secure handling of customer PII and **Leads**; secrets hygiene (no credentials in code — enforced by GitHub **secret scanning** and `main` protection); secure-coding basics for the Laravel/PHP 8.4 stack; safe use of LLM inference (chat content is sent to **Scaleway** for inference — no off-platform AI tools for customer data); incident reporting; and 2FA/passkey use.
- Training content is reviewed annually and on material change to the stack or threat landscape.

### 5.2 Acceptable use acknowledgement

- Workers **shall** read and acknowledge the **Acceptable Use Policy** at hire and at each annual training cycle.
- Acceptable use includes mandatory device security (full-disk encryption, screen lock, current OS patches), use of the password manager, and prohibition of storing customer PII on personal/unmanaged storage.

### 5.3 Authentication and access controls

- All Chatvia accounts **shall** use strong, unique credentials. Application accounts authenticate via **Laravel Fortify + Sanctum**, with passwords hashed using **bcrypt**.
- **Two-factor authentication is mandatory** for all staff accounts and any account with administrative or production access. Chatvia supports **TOTP and passkeys** (`config/fortify.php`, `settings/two-factor`). The planned Filament admin panel **shall enforce staff 2FA** at the panel boundary.
- Authorization follows **least privilege**: roles via `UserType` (Admin / Workspace / Employee / Chatter), enforced by per-resource **Policies** and **TeamMembership** grants. Workers receive only the roles their function requires.
- 2FA is also required on GitHub and the Hetzner and Scaleway consoles; all support hardware/passkey factors.

## 6. Onboarding — Access Provisioning

When a worker joins, the Founder **shall**, before granting access and recording each step:

1. Confirm a signed NDA/confidentiality agreement and acknowledged Acceptable Use Policy are on file.
2. Confirm screening (§4.1) is complete where lawful.
3. Provision the **minimum** accounts required for the role (GitHub with least-privilege repo access; the Hetzner and Scaleway consoles only if infrastructure work is in scope; application `UserType`/role).
4. Enforce 2FA enrollment (TOTP or passkey) before first production access.
5. Assign training; production access is withheld until at-hire training is complete.
6. Record the grant — accounts, roles, and date — in the access register.

## 7. Offboarding — Timely Revocation

On termination, contract end, or role change, the Founder **shall**, **on the worker's last day or immediately on involuntary termination**:

1. **Revoke all access**: deactivate the application account; remove GitHub collaborator/team membership; revoke Hetzner and Scaleway console access; rotate any shared secrets the worker could have known; revoke issued **Sanctum API tokens** and **OAuth tokens** they held.
2. **Rotate credentials** that cannot be individually attributed (shared service keys, `APP_KEY` only if exposure is suspected).
3. **Recover assets** and confirm secure deletion of any customer data held locally, per the confidentiality terms.
4. Confirm continuing confidentiality obligations in writing.
5. Record completion of each step in the access register with the date.

**Target:** access revocation completed **within one business day**, and immediately for involuntary terminations. Because change history is immutable in Git and tool-call activity is audited (`agent_activity`, spatie/activitylog), any access used after revocation is detectable.

## 8. Periodic Access Review

- The Founder **shall** review all worker access grants **at least quarterly** against current roles, removing any standing access no longer required.
- Reviews cover GitHub membership, Hetzner and Scaleway console users, application roles/`UserType`, and outstanding API/OAuth tokens. Findings and remediations are recorded.

## 9. Disciplinary Process

Violations of this policy, the Acceptable Use Policy, or confidentiality terms are handled through a documented disciplinary process proportionate to the severity and intent of the violation, consistent with Denmark employment law. Suspected willful misuse of customer data triggers the incident-response process and may result in immediate access revocation pending investigation.

## 10. Records

The access register, signed NDAs, training-completion records, and self-review notes are retained as **Internal/Confidential** for the duration of engagement plus the period required by Denmark law, and are available to auditors on request.

## 11. Enforcement and Exceptions

This policy is mandatory. Exceptions require written approval from the Founder / Security Lead, must state a compensating control and expiry, and are recorded in the exception register.

## 12. Contacts

- Privacy matters: **info@chatvia.ai**
- Security matters and incident reporting: **info@chatvia.ai**

## 13. Mapping

| Framework | Controls |
|---|---|
| **SOC 2 Common Criteria** | CC1.1 (integrity & ethics — NDA, AUP), CC1.4 (competence — screening, training), CC1.5 (accountability), CC2.2 (internal communication — training/acknowledgement), CC5.3 (policies & procedures), CC6.1–CC6.3 (logical access — least privilege, Policies/`UserType`, provisioning), CC6.2 (timely revocation on termination), CC6.6 (authentication — 2FA/passkeys) |
| **ISO/IEC 27001:2022 Annex A** | A.5.9 (inventory/access register), A.5.10 (acceptable use), A.5.11 (return of assets), A.5.15–A.5.18 (access control, identity, authentication, access rights), A.6.1 (screening), A.6.2 (terms & conditions of employment), A.6.3 (security awareness, education & training), A.6.4 (disciplinary process), A.6.5 (responsibilities after termination/change), A.6.6 (confidentiality/NDAs) |

---

> Template prepared 2026-06-09. Review with qualified legal/security counsel before relying on it externally.
