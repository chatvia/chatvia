# Acceptable Use Policy

| | |
|---|---|
| **Owner** | Founder / Security Lead |
| **Version** | 1.0 |
| **Effective date** | 2026-06-09 |
| **Review cadence** | Annual (or on material change) |
| **Classification** | Internal |

## 1. Purpose

This policy defines how Akkadian ("Chatvia") staff, contractors, and any other authorised individual ("Users") may use Chatvia systems, endpoints, vendor consoles, and the customer data Chatvia processes. Chatvia operates an embeddable AI chatbot widget that collects EU visitor PII and converts captured contacts into Leads. Chatvia acts as a **data processor**; its workspace customers are **controllers**. Misuse of Chatvia systems can expose controller data, customer transcripts, Leads, API keys, and OAuth tokens — this policy exists to prevent that.

## 2. Scope

This policy applies to:

- All Users with access to Chatvia source code, production infrastructure, or vendor consoles.
- All endpoints (laptops, mobile devices) used to access Chatvia systems or data.
- All Chatvia systems: the Laravel application, Postgres (pgvector/bm25), Redis, Reverb, Horizon, and all third-party consoles (Hetzner, Scaleway, GitHub, Flare, payment provider, DNS/email).
- All customer data: conversation transcripts, Leads, uploaded knowledge-base documents, vector embeddings, API keys, and OAuth tokens.

## 3. Solo-operator note (compensating control)

Chatvia is operated by a solo founder plus occasional contractors. Where this policy implies segregation of duties, the **documented compensating control** is: a single accountable engineer; all production-affecting changes flow through GitHub Pull Requests gated by CI (`.github/workflows/tests.yml`); deploys are tag-gated on the test job (`.github/workflows/deploy.yml`); `main` branch protection and secret scanning are enabled; and the founder performs a periodic self-review of access, audit logs, and merged changes against immutable Git history.

## 4. Data classification

Users shall handle data per its classification. The highest applicable class governs.

| Class | Examples | Handling |
|---|---|---|
| **Restricted** | Leads/visitor PII, conversation transcripts, customer-uploaded documents, API keys, OAuth tokens, `APP_KEY`, DB/vendor credentials | Production-only. Never copied to endpoints, chat tools, personal accounts, or AI tools outside the sanctioned Scaleway inference path. Access on a strict need-to-operate basis. |
| **Confidential** | Source code, infrastructure config, security findings, contracts, financials | Internal only. Source via GitHub; secrets via environment/secret store, never committed. |
| **Internal** | Runbooks, this policy, internal docs | Not for public release. |
| **Public** | Marketing site, public docs | No restriction; must not embed internal-stack detail (public docs use generic terms, e.g. "bearer token", not framework internals). |

Restricted data must never leave Chatvia's EU hosting boundary — Hetzner (Germany) for compute, database, and Redis, Scaleway (France) for AI inference, object storage, and backups — except via sanctioned flows: Scaleway LLM inference, Scaleway S3-compatible storage, and the controller-facing `DataExport` subsystem.

## 5. Endpoint security

Any endpoint used to access Chatvia systems or data shall:

- Run a supported, patched OS with automatic security updates enabled.
- Enforce **full-disk encryption** (FileVault / BitLocker / LUKS).
- Lock automatically after no more than 5 minutes idle, with a strong passcode/biometric.
- Run host firewall and reputable anti-malware where the platform supports it.
- Keep browsers and the local toolchain (PHP 8.4, Node, Composer, npm) current; CI runs `composer audit` and `npm audit` but the local environment is the User's responsibility.

Lost or stolen endpoints must be reported to info@chatvia.ai **immediately** so credentials and sessions can be rotated and revoked.

## 6. Authentication and credentials

- **No shared credentials.** Every console, server, and database account is individually owned. Accounts must never be shared between the founder and contractors, or among contractors.
- **MFA is mandatory on every vendor console** — GitHub, Hetzner, Scaleway, Flare, DNS/email, payment provider, and any console holding Restricted or Confidential data. Prefer phishing-resistant factors (passkeys/TOTP) over SMS.
- Application access uses **Laravel Fortify + Sanctum**; Users with application logins shall enable 2FA (TOTP or passkeys) at `settings/two-factor` (`config/fortify.php`). The forthcoming Filament admin panel will enforce staff 2FA.
- Credentials, API keys, OAuth tokens, and the `APP_KEY` shall be stored only in the secret store / deploy environment — **never** in source, Git history, tickets, chat, or screenshots. GitHub secret scanning is enabled as a backstop; a triggered alert requires immediate rotation.
- Passwords shall be unique and generated/stored in a password manager. Application passwords are hashed with bcrypt; Users must not attempt to recover or reuse plaintext.
- Access is granted on least privilege via `UserType` (Admin/Workspace/Employee/Chatter) + per-resource Policies + TeamMembership. Contractor access is time-boxed and revoked at engagement end.

## 7. Acceptable use

Users may use Chatvia systems and data **only** to build, operate, support, and secure the Chatvia service, and only to the minimum extent needed for the task at hand. Specifically Users shall:

- Access Restricted customer data only when a concrete operational or support task requires it, and prefer aggregated or de-identified data where it suffices.
- Make all production-affecting changes via PR + CI; never push directly to `main` or hot-patch production outside the documented process.
- Treat the immutable tool-call audit (`agent_activity`, spatie/activitylog), Flare, Pulse, Telescope, and Nightwatch as monitoring of record — not to be disabled, bypassed, or tampered with.
- Use Chatvia-sanctioned tools and the EU inference path only; route LLM/chat content solely through the configured Scaleway (Qwen3) integration.

## 8. Prohibited activities

Users shall **not**:

- Share, reuse, or embed credentials; or access any system using another person's account.
- Copy, export, screenshot, or transmit Restricted data (Leads, transcripts, documents, keys, tokens) to personal devices, personal cloud/email, messaging apps, or third-party AI tools outside the sanctioned Scaleway path.
- Paste customer transcripts, Leads, documents, or secrets into any external LLM, code assistant, or pastebin.
- Move customer data outside the EU hosting boundary, or process it for any purpose beyond delivering the contracted service (no secondary use, no model training on controller data without documented controller instruction).
- Disable, weaken, or circumvent security controls: TLS / Postgres `sslmode=require`, `SESSION_ENCRYPT`, CSP/HSTS/security headers (`ContentSecurityPolicy` middleware), per-route rate limiting, branch protection, CI gates, or MFA.
- Commit secrets, disable secret scanning, force-push over protected history, or merge to bypass a failing CI job.
- Introduce unvetted dependencies; ignore Dependabot alerts (`.github/dependabot.yml`) or unaddressed `composer audit` / `npm audit` failures.
- Run penetration testing, scanning, or destructive testing against production without prior written authorisation (annual third-party pentest is conducted under contract when a deal requires).
- Use Chatvia systems for unlawful activity, harassment, crypto-mining, or to access data unrelated to an assigned task.
- Install unauthorised software on production hosts or connect unmanaged infrastructure to Chatvia services.

## 9. Handling confidential and Restricted data

- Restricted data is processed in production only; local copies are prohibited. Where a `DataExport` is generated for a controller, it is delivered through the sanctioned subsystem and not retained on endpoints.
- Retention follows the configured windows (`config/billing.php`, e.g. 90 / 180 / 366 days by plan) and scheduled prune jobs; Users shall not retain data beyond these windows or outside system-managed storage.
- Deletion and export requests from controllers are fulfilled only through the application's retention/`DataExport` mechanisms, preserving the processor↔controller boundary.
- Source code and infrastructure config (Confidential) stay within GitHub and Chatvia infrastructure; do not fork to personal accounts or paste into external tools.

## 10. Monitoring, reporting, and enforcement

- Chatvia monitors system access and activity (audit log, Flare, Pulse, Telescope, Nightwatch) and may review it to enforce this policy.
- Users shall report suspected policy violations, lost devices, exposed secrets, or security incidents to info@chatvia.ai without delay, and privacy/data-subject concerns to info@chatvia.ai.
- Violations may result in revoked access, termination of engagement, and — where unlawful — referral to authorities under the laws of Denmark.
- Acknowledgement of this policy is a condition of access for all staff and contractors.

## 11. Mapping

| Framework | Controls |
|---|---|
| **SOC 2 (TSC Common Criteria)** | CC1.1, CC1.4 (integrity, competence, accountability); CC2.2 (internal communication of policy); CC5.2 (control activities via technology); CC6.1 (logical access / least privilege); CC6.2, CC6.3 (credential issuance, MFA, removal); CC6.6 (boundary protection); CC6.7 (data transmission/movement restrictions); CC6.8 (unauthorised/malicious software); CC7.2 (monitoring); CC7.3, CC7.4 (incident response) |
| **ISO/IEC 27001:2022 Annex A** | A.5.10 (acceptable use of information and assets); A.5.11 (return of assets); A.5.15 (access control); A.5.17 (authentication information); A.5.18 (access rights); A.5.24/A.5.26 (incident management); A.6.3 (awareness); A.8.1 (user endpoint devices); A.8.2/A.8.3 (privileged/access restriction); A.8.5 (secure authentication); A.8.7 (malware protection); A.8.10/A.8.12 (deletion / data leakage prevention); A.8.24 (cryptography); A.8.28 (secure coding) |

---

> Template prepared 2026-06-09. Review with qualified legal/security counsel before relying on it externally.
