# Cryptography & Encryption Policy

| | |
|---|---|
| **Owner** | Founder / Security Lead |
| **Version** | 1.0 |
| **Effective date** | 2026-06-09 |
| **Review cadence** | Annual (or on material change) |
| **Classification** | Internal |

## 1. Purpose

This policy defines how Akkadian ("Chatvia") protects the confidentiality and integrity of data in transit and at rest through cryptography. As a **data processor** for its customer workspaces (the **controllers**), Chatvia processes EU visitor PII captured by the embeddable widget (Leads), conversation transcripts, knowledge-base documents, vector embeddings, API keys, and OAuth tokens. Strong, correctly-managed cryptography is a primary control protecting that data and is required to honour Chatvia's obligations under its Data Processing Agreements.

## 2. Scope

This policy covers all Chatvia production systems hosted in the EU — Hetzner (Germany) for compute & database, Scaleway (France) for AI inference, object storage, backups & email: the Laravel 13 / PHP 8.4 application, Postgres (pgvector + bm25), Redis, Reverb websockets, Horizon queues, and Scaleway S3-compatible object storage. It applies to the founder and any contractor with access to production systems, source code, or secrets.

## 3. Approved algorithms

Chatvia shall use only modern, industry-accepted primitives. Deprecated algorithms (MD5, SHA-1 for signatures, DES, RC4, SSLv3, TLS < 1.2) are prohibited.

| Purpose | Algorithm | Where |
|---|---|---|
| Transport | TLS 1.2+ (1.3 preferred) | All HTTP, websocket, DB, S3 traffic |
| Application encryption at rest | AES-256-CBC + HMAC (Laravel `encrypter`) | `config/app.php` → `'cipher' => 'AES-256-CBC'` |
| Password hashing | bcrypt | Laravel default hasher (Fortify) |
| 2FA | TOTP (RFC 6238); WebAuthn/passkeys | `config/fortify.php`, `settings/two-factor` |
| Random tokens | CSPRNG (`random_bytes` / `Str::random`) | API keys, session IDs, OAuth state |

## 4. Encryption in transit

- **Public endpoints.** All inbound traffic — the Inertia/Vue app, the embeddable widget, the REST API, and Reverb websockets — is served over TLS only. HTTP is redirected to HTTPS. **HSTS** is enforced via the `ContentSecurityPolicy` middleware alongside `X-Frame-Options`, `X-Content-Type-Options`, and CSP headers, so browsers refuse plaintext downgrades.
- **Database.** Production connects to the Hetzner-hosted Postgres with `sslmode=require`, set through `DB_SSLMODE` in `config/database.php`. The committed default is `prefer` for local development; production environments **must** set `DB_SSLMODE=require` so an unencrypted DB connection is rejected. This is a release-gate checklist item.
- **Object storage & queues.** Attachment and export traffic to Scaleway S3-compatible storage uses HTTPS endpoints. Redis (cache/queue/Horizon) and Reverb run within the trusted EU network boundary; cross-host links use TLS where the transport leaves the host.
- **AI inference.** Chat content sent to Scaleway for Qwen3 inference travels over TLS to an EU-region endpoint. No inference traffic leaves the EU.

## 5. Encryption at rest

- **Session payloads.** `SESSION_ENCRYPT=true` (`config/session.php`) encrypts session data at rest with the application key before it is persisted.
- **Application-level field encryption.** Sensitive secrets are stored using Laravel's `encrypted` cast (AES-256-CBC + authenticated HMAC), so the plaintext never lands in the database. Current encrypted fields include:
  - `AgentMcpServer`: `headers`, `auth_value`, `oauth_refresh_token`, `oauth_client_secret`
  - `AgentFunction`: `webhook_headers`, `webhook_secret`, `auth_config`
  - `ConnectorOAuthClient`: `client_secret`, `registration_access_token`
  - Telegram bot tokens and other third-party credentials carry the same cast.
  Any new column holding a credential, third-party token, or secret **shall** use the `encrypted` / `encrypted:array` cast — this is a code-review gate.
- **Passwords.** User passwords are never stored or logged in plaintext; they are bcrypt-hashed by the framework hasher. Verification is constant-time.
- **Storage-layer encryption.** Postgres (Hetzner) and S3-compatible buckets (Scaleway) reside on provider-managed, encrypted-at-rest infrastructure (provider-managed volume encryption), providing defence in depth beneath application-level encryption.
- **API keys.** Sanctum personal-access tokens are stored as SHA-256 hashes; the plaintext token is shown to the user once at creation and is unrecoverable thereafter.

## 6. Key management

### 6.1 The application key (`APP_KEY`)

`APP_KEY` is the AES-256 master key backing all `encrypted` casts and session encryption. It is the single most sensitive secret Chatvia holds.

- **Generation.** Created via `php artisan key:generate` (CSPRNG-derived 256-bit key).
- **Storage.** Held only in the production environment's secret store (deployment platform secrets / encrypted `.env`). It is **never** committed to Git; GitHub **secret scanning** and `.gitignore` guard against accidental commit.
- **Access.** Restricted to the founder and the deployment pipeline. Loss of `APP_KEY` renders all encrypted fields and sessions undecryptable.

### 6.2 Escrow

A sealed copy of the current `APP_KEY` (and other root secrets) is held in **escrow** in an offline password manager / secrets vault, separate from the production environment and from Git, so a single-host failure cannot permanently lock encrypted data. The escrow copy is updated on every key rotation.

### 6.3 Rotation

- **Cadence.** `APP_KEY` is reviewed annually and rotated on suspected compromise, contractor offboarding, or platform migration.
- **Procedure.** Because rotating `APP_KEY` invalidates data encrypted with the prior key, rotation follows Laravel's `APP_PREVIOUS_KEYS` pattern: the new key becomes `APP_KEY`, the prior key is retained in `APP_PREVIOUS_KEYS` so existing ciphertext still decrypts, encrypted fields are re-encrypted with the new key via a maintenance job, then the prior key is retired and removed from escrow's active set.
- **Other secrets.** OAuth client secrets, Scaleway API credentials, and webhook secrets are rotated on the same triggers; revocation is performed at the upstream provider.

### 6.4 Certificates

TLS certificates are provisioned and auto-renewed by the hosting platform / ACME. Expiry is monitored (Flare / uptime checks); manual review confirms renewal at the annual policy review.

## 7. Segregation of duties — compensating control

Chatvia operates as a sole engineer with occasional contractors, so cryptographic operations cannot be split across separate key-custodian and operator roles. The documented compensating controls are:

- All code touching encryption (casts, key handling, config) merges only via **GitHub PR**, gated by CI (`.github/workflows/tests.yml`: Pest, `composer audit`, `npm audit`, Pint, Larastan, ESLint) on a **branch-protected `main`**.
- Production deploys are tag-driven (`v*`) and gated on the passing test job (`.github/workflows/deploy.yml`).
- The full change history is **immutable in Git**; secret scanning runs on every push.
- The founder performs a **periodic self-review** of secret access, encrypted-field coverage, and key inventory at each annual review, recording the outcome.

## 8. Prohibited practices

- Hard-coding keys, passwords, or tokens in source, config committed to Git, logs, or error reports (Flare/Telescope/Pulse scrubbing applies).
- Inventing custom cryptographic schemes; only vetted framework/library primitives are used.
- Transmitting credentials or PII over unencrypted channels.
- Disabling `SESSION_ENCRYPT`, `DB_SSLMODE=require`, or HSTS in production.

## 9. Roles & responsibilities

The Founder / Security Lead owns key generation, escrow, rotation, and this policy. Contractors must follow it and are bound by NDA. Security concerns are reported to **info@chatvia.ai**; data-subject and privacy matters to **info@chatvia.ai**.

## 10. Review

Reviewed annually or on material change (new secret type, algorithm deprecation, key compromise, platform migration). The review confirms the encrypted-field inventory, the key/escrow inventory, and that all production cryptographic settings remain enforced.

## Mapping

| Framework | Controls |
|---|---|
| **SOC 2** | CC6.1 (logical access — encryption of data at rest/in transit, key restriction), CC6.6 (protection of data transmitted over public networks — TLS/HSTS), CC6.7 (encryption of data, secret handling), CC6.8 (prevention of unauthorized software — change gates), CC7.1 (config monitoring) |
| **ISO/IEC 27001:2022 Annex A** | A.8.24 (Use of cryptography), A.8.5 (Secure authentication — bcrypt, 2FA), A.5.33 (Protection of records), A.8.12 (Data leakage prevention — secret scanning), A.8.31/A.8.32 (Separation of environments & change management), A.5.23 (Information security for cloud services — Scaleway EU) |

> Template prepared 2026-06-09. Review with qualified legal/security counsel before relying on it externally.
