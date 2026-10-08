# Authentication & Password Policy

| | |
|---|---|
| **Owner** | Founder / Security Lead |
| **Version** | 1.0 |
| **Effective date** | 2026-06-09 |
| **Review cadence** | Annual (or on material change) |
| **Classification** | Internal |

## 1. Purpose

This policy defines how Akkadian ("Chatvia") authenticates human users and machine clients, how credentials are created, stored, rotated, and revoked, and the controls that protect authenticated sessions. It exists to prevent unauthorised access to workspace accounts, conversation transcripts, captured Leads (contact PII), knowledge-base documents, API keys, and OAuth tokens held by Chatvia in its role as a **data processor** for its customers.

Chatvia is operated by a sole engineer with occasional contractors. Where a control normally assumes segregation of duties, the **documented compensating control** applies: every change reaches production only via GitHub Pull Request, CI gating, immutable Git history, and periodic self-review (see §9).

## 2. Scope

- **Workspace and team users** — `UserType::Workspace` and `UserType::Employee` accounts that sign in to the Chatvia dashboard.
- **Chatter / end-user accounts** — `UserType::Chatter` identities created inside embedded widgets.
- **Administrative access** — `UserType::Admin` and the planned Filament admin panel.
- **Machine clients** — API consumers authenticating with Sanctum personal access tokens.

It covers the full authentication stack: Laravel Fortify, Laravel Sanctum, and the session, cookie, and rate-limiting layers around them.

## 3. Identity & roles

Chatvia shall enforce least privilege through layered authorization:

- **Coarse role** — `UserType` (Admin / Workspace / Employee / Chatter) gates which surfaces a principal may reach.
- **Per-resource authorization** — Laravel Policies authorize each model action (e.g. `AgentPolicy`, `TeamPolicy`); no controller mutates a resource without a passing policy check.
- **Team membership** — `TeamMembership` and `AgentMembership` scope a teammate's access to specific workspaces and agents.

Role and membership changes are code- or database-mediated only; there is no self-service privilege escalation path.

## 4. Password requirements

### 4.1 Complexity & breached-password checks

All interactive password setting and reset flows route through Laravel's `Password` validation rule (`RegisterUserRequest`, `UpdatePasswordRequest`, `ResetUserPassword`, `AcceptAgentInvitationRequest`, `AcceptInvitationRequest`). Chatvia shall configure the application-wide `Password::defaults()` rule to enforce, at minimum:

- **Length** ≥ 12 characters.
- **Mixed case, at least one number, and at least one symbol.**
- **Breached-password rejection** via `->uncompromised()`, which checks the candidate against the Have I Been Pwned corpus using k-anonymity (only a partial SHA-1 prefix leaves the server; the plaintext never does).

Because the rule is defined once in the service provider, every entry point inherits the standard automatically — there is no per-form drift.

### 4.2 Reset & recovery

- Password reset uses Fortify's signed, time-limited reset-link broker (`Features::resetPasswords()`); links expire and are single-use.
- The "forgot password" endpoint responds identically for known and unknown addresses (`ForgotPasswordController`) to prevent account enumeration.
- Email verification is enabled (`Features::emailVerification()`).

### 4.3 No shared credentials

Each human user holds an individual account. Shared logins are prohibited. Service-to-service access uses Sanctum tokens (§7), never shared passwords.

## 5. Credential storage

- **Password hashing** — passwords are stored only as bcrypt hashes (Laravel's default `Hash` driver). Plaintext passwords are never logged, stored, or transmitted to third parties.
- **Application-level encryption** — sensitive payloads use Laravel's `APP_KEY` (AES-256-CBC). `APP_KEY` and all secrets are held outside source control and injected via environment; GitHub secret scanning is enabled to catch accidental commits.
- **OAuth tokens & API keys** stored for connectors are treated as secrets at rest under the same encryption regime.

## 6. Multi-factor authentication (MFA)

Chatvia provides and shall promote MFA for all dashboard users:

- **TOTP** — time-based one-time passwords via `Features::twoFactorAuthentication()`, enrolled and managed at `settings/two-factor`, with recovery codes issued at setup.
- **Passkeys (WebAuthn)** — phishing-resistant hardware/platform authenticators via `Features::passkeys()`.
- Both flows require **password confirmation** before enrolment changes (`'confirmPassword' => true`), preventing a hijacked-but-unconfirmed session from altering MFA.

**Administrative MFA shall be mandatory.** The planned Filament admin panel will enforce 2FA for any `UserType::Admin` / staff session. Until that panel ships, the sole administrator runs with TOTP and/or a passkey enrolled, recorded as the operative compensating control.

## 7. API token handling (Sanctum)

- API clients authenticate with **Laravel Sanctum personal access tokens**, stored as SHA-256 hashes (Sanctum never persists the plaintext after issuance).
- Tokens carry **abilities (scopes)** so a key is limited to the operations it needs.
- **Expiry** is enforced via `SANCTUM_TOKEN_TTL_MINUTES` (`config/sanctum.php`); Chatvia shall set a finite, non-default TTL for production rather than effectively-never expiry.
- A configurable **`token_prefix`** is set in production to enable secret-scanning vendors and GitHub to detect leaked Chatvia tokens.
- Tokens are **revocable** by the owning user at any time; revocation is immediate.
- Customers are instructed (in customer-facing docs) to treat tokens as bearer secrets and rotate on suspected exposure.

## 8. Session & cookie security

Authenticated browser sessions are hardened in `config/session.php`:

- **Encrypted at rest** — `SESSION_ENCRYPT=true`; session payloads are AES-encrypted, not plaintext.
- **`HttpOnly`** — session cookies are not readable by JavaScript (`http_only=true`), reducing XSS token theft.
- **`Secure`** — cookies are flagged secure in production (`SESSION_SECURE_COOKIE`), so they traverse HTTPS only.
- **`SameSite=Lax`** — limits cross-site cookie transmission, mitigating CSRF.
- **Lifetime** — `SESSION_LIFETIME` bounds idle session validity (default 120 minutes); Chatvia shall keep this short for privileged sessions.
- All authenticated traffic is served over **TLS** with HSTS, X-Frame-Options, X-Content-Type-Options, and CSP headers applied by the `ContentSecurityPolicy` middleware.

## 9. Lockout & throttling

Brute-force and credential-stuffing resistance is enforced by named rate limiters registered in `FortifyServiceProvider::registerRateLimiters()`:

| Surface | Limit |
|---|---|
| **Login** | 5 attempts/minute keyed on `email\|IP`, **and** 30 attempts/hour keyed on IP |
| **Two-factor challenge** | 5/minute and 20/hour per session/user key |
| **Passkey challenge** | 10/minute per key |

Login throttling keys on a transliterated `email + IP` composite so a single account cannot be hammered and a single IP cannot fan out across accounts. General per-route rate limiting protects the wider API surface. Exhausted limits return HTTP 429.

## 10. Monitoring & response

- Authentication-relevant errors surface in **Flare**; runtime and request telemetry in **Laravel Pulse**, **Telescope**, and **Nightwatch**.
- Privileged tool actions are written to an **immutable audit log** (`agent_activity`, spatie/activitylog).
- Suspected credential compromise triggers the Incident Response process: revoke affected sessions/tokens, force password reset, and notify affected controllers per the processor obligations. Security contact: **info@chatvia.ai**. Privacy/data-subject contact: **info@chatvia.ai**.

## 11. Roles & responsibilities

- **Founder / Security Lead** — owns this policy, configures the `Password::defaults()` rule, Sanctum TTL, and rate limiters, enrols administrative MFA, and reviews authentication telemetry.
- **Contractors** — receive individual, least-privilege accounts with MFA; access is revoked at engagement end.

## 12. Exceptions & review

Exceptions require written approval from the Founder / Security Lead, recorded with a justification and expiry. This policy is reviewed at least **annually** or on material change to the authentication stack.

## 13. Mapping

**SOC 2 Common Criteria**

- **CC6.1** — logical access controls: `UserType`, Policies, `TeamMembership`, password/MFA requirements.
- **CC6.2** — credential issuance & revocation: individual accounts, Sanctum token issue/revoke.
- **CC6.3** — least privilege & role-based access: per-resource Policies, token abilities.
- **CC6.6 / CC6.7** — protection against external threats and in-transit data: TLS/HSTS, encrypted `HttpOnly` `Secure` cookies, login/MFA rate limiting.
- **CC6.8** — protection of stored credentials: bcrypt hashing, AES app-level encryption, hashed Sanctum tokens.
- **CC7.2** — monitoring & anomaly detection: Flare, Pulse, `agent_activity` audit log.

**ISO/IEC 27001:2022 Annex A**

- **A.5.15** — access control.
- **A.5.16** — identity management (individual accounts, `UserType`).
- **A.5.17** — authentication information (bcrypt, breached-password checks, Sanctum tokens).
- **A.5.18** — access rights provisioning/revocation.
- **A.8.2** — privileged access rights (admin MFA, Filament panel).
- **A.8.3** — information access restriction (Policies, token abilities).
- **A.8.5** — secure authentication (MFA: TOTP + passkeys; rate-limited challenges).
- **A.8.24** — use of cryptography (TLS, AES sessions, bcrypt, `APP_KEY`).

> Template prepared 2026-06-09. Review with qualified legal/security counsel before relying on it externally.
