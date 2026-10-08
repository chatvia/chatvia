# Access Control Policy

| | |
|---|---|
| **Owner** | Founder / Security Lead |
| **Version** | 1.0 |
| **Effective date** | 2026-06-09 |
| **Review cadence** | Annual (or on material change) |
| **Classification** | Internal |

## 1. Purpose

This policy defines how Akkadian ("Chatvia") grants, manages, reviews, and revokes access to the systems and data that operate the Chatvia platform. Chatvia is an embeddable AI chatbot SaaS that processes EU visitor PII (conversation transcripts and captured **Leads**) on behalf of its customers. Chatvia acts as a **data processor**; customer workspaces are the **controllers**. Disciplined access control is therefore a contractual and regulatory obligation, not only a security one.

## 2. Scope

This policy applies to:

- The Chatvia application (Laravel 13 / PHP 8.4 / Inertia + Vue 3), its Postgres (pgvector + bm25), Redis, Reverb, and Horizon tiers.
- Operational dashboards: Laravel Horizon, Telescope, Pulse, Nightwatch, and Flare.
- The Hetzner (Germany/EU) hosting account (compute, PostgreSQL, Redis), the Scaleway (France/EU) account, its S3-compatible object storage, and the Scaleway LLM inference endpoints (Qwen3).
- The GitHub organization, CI/CD pipelines, and the production deployment toolchain.
- All persons with access: the founder, contractors, and any future staff.

## 3. Principles

- **Least privilege.** Every identity receives the minimum access required for its function. Default-deny; access is granted explicitly.
- **Need-to-know.** Access to conversation transcripts, Leads, knowledge-base documents, API keys, and OAuth tokens is limited to roles whose function requires it.
- **Authorization at the resource boundary.** Authorization is enforced in code via per-resource Laravel Policies and tenant scoping, never by UI obscurity alone.
- **Tenant isolation.** A workspace's data is never accessible to another workspace. Every query is scoped to the authenticated workspace.
- **Accountability.** Privileged and tool-invoking actions are logged to an immutable audit trail.

## 4. Identity & Role Model

Identity is discriminated by the `UserType` enum (`users.type`, `src/Support/Enums/User/UserType.php`):

| Role | Capability | Data reach |
|---|---|---|
| **Admin** | Internal Chatvia staff. Full platform access; the only role that may reach ops dashboards. | All tenants (break-glass / support only). |
| **Workspace** | Account owner — signs up, manages the team, pays. | Own workspace only. |
| **Employee** | Collaborator invited into a workspace team. Scoped by `TeamMembership` role. | Own workspace, within granted team role. |
| **Chatter** | End-user who only logs into `/chat`. No dashboard access; must be granted per-agent access via the `agent_user` pivot. | Only agents explicitly granted. |

Authorization is layered:

1. **`UserType`** sets the coarse capability tier.
2. **Per-resource Policies** (e.g. `AgentPolicy`) gate each model action (`view`, `update`, `delete`) and enforce workspace ownership.
3. **`TeamMembership`** refines what an Employee may do inside a workspace.
4. **`AgentMembership`** (`agent_user`) gates Chatter access to individual agents.

No code path may bypass these checks. New gated models ship with a Policy in the same change set.

## 5. Authentication & MFA

- Authentication is handled by **Laravel Fortify** (sessions) and **Sanctum** (API tokens). Passwords are hashed with **bcrypt**.
- **Multi-factor authentication** is available to all dashboard users: **TOTP** and **passkeys (WebAuthn)** are enabled Fortify features (`config/fortify.php`; user-facing at `settings/two-factor`).
- **Staff MFA is mandatory.** The founder and any future Admin/staff account shall have 2FA enabled. The planned **Filament admin panel** will enforce staff 2FA at the panel boundary; until it ships, the compensating control is that the sole Admin account has TOTP/passkey enabled and this is confirmed at each quarterly access review.
- Workspace customers are strongly encouraged to enable 2FA; account-takeover risk is theirs as controllers, but Chatvia provides the mechanism.
- API tokens (Sanctum) and customer-issued API keys are bearer credentials, scoped, revocable, and never logged in plaintext.

## 6. Privileged Access — Ops Dashboards

Horizon, Telescope, and Pulse expose queue internals, request payloads, and metrics that may contain customer data. Access is **gated to `isAdmin`** via the framework's dashboard authorization gates — these dashboards are not reachable by Workspace, Employee, or Chatter identities, and are not exposed on public routes.

- Access is **break-glass**: used only for incident response, debugging, or support, and only on the minimum data needed.
- Admin actions against tenant data are logged where they pass through the application (immutable tool-call audit in `agent_activity` via spatie/activitylog; application errors and traces in Flare).
- Production infrastructure access (the Hetzner and Scaleway consoles, the Hetzner-hosted PostgreSQL, object storage, DNS) is restricted to the founder, protected by MFA on the provider accounts, and treated as the highest-sensitivity credential set.

## 7. Joiner / Mover / Leaver

Chatvia currently operates as a **solo founder plus occasional contractors**. The lifecycle process is documented even where one person fills several steps, so it scales when staff are added.

**Joiner**

- Access is provisioned least-privilege for the specific engagement. Contractors receive only the repositories, environments, and credentials their task requires — never standing production data access by default.
- A scoped GitHub invitation and (where needed) time-boxed credentials are issued. The grant and its scope are recorded.

**Mover**

- On a change of responsibility, access is re-scoped within one business day: privileges no longer needed are removed before new ones are added.

**Leaver**

- On contract end, all access is revoked **same day**: GitHub removal, Sanctum/API token revocation, rotation of any shared secrets the person could have seen, and revocation of Hetzner/Scaleway console access.
- Secrets potentially exposed to a departing contractor (API keys, OAuth tokens, `APP_KEY` only if exposure is plausible) are rotated.

## 8. Periodic Access Review

- **Quarterly**, the founder reviews every active identity across GitHub, the Hetzner and Scaleway accounts, the application's Admin users, and any contractor credentials, confirming each still warrants its access and that least privilege holds.
- The review confirms: no orphaned/contractor accounts remain active, staff 2FA is enabled, and Admin (`UserType::Admin`) accounts are limited to legitimate staff.
- Each review is dated and recorded (date, reviewer, accounts checked, actions taken) for audit evidence.

## 9. Segregation of Duties — Compensating Controls

True segregation of duties is impossible with a sole engineer. The documented compensating controls are:

- **All production change lands via GitHub Pull Request.** The `main` branch is protected; **secret scanning** is enabled.
- **CI gates every change** — Pest tests, `composer audit`, `npm audit`, Pint, Larastan, and ESLint run on push/PR (`.github/workflows/tests.yml`).
- **Production deploys are tag-gated** (`v*`) and blocked unless the test job passes (`.github/workflows/deploy.yml`); no direct-to-production changes.
- **Immutable Git history** provides a complete, attributable change record.
- **Periodic self-review** at each access review, plus an annual third-party penetration test of the app and widget (scheduled when a deal requires), substitutes for an independent second reviewer.

## 10. Enforcement

Violations of this policy (e.g. provisioning standing access without least-privilege justification, skipping a leaver revocation, or sharing privileged credentials) are remediated immediately and noted in the next access review. Access may be suspended pending remediation. Questions or suspected violations: info@chatvia.ai; data-subject and privacy matters: info@chatvia.ai.

## 11. Mapping

| Control area | SOC 2 (CC) | ISO 27001:2022 Annex A |
|---|---|---|
| Logical access / least privilege | CC6.1, CC6.2, CC6.3 | A.5.15, A.5.18, A.8.2, A.8.3 |
| Authentication & MFA | CC6.1 | A.5.17, A.8.5 |
| Privileged access (ops dashboards) | CC6.1, CC6.3 | A.8.2, A.8.18 |
| Joiner/mover/leaver | CC6.2, CC6.3 | A.5.16, A.6.1, A.6.5, A.5.18 |
| Periodic access review | CC6.2, CC6.3 | A.5.18 |
| Segregation of duties / change control | CC6.1, CC8.1 | A.5.3, A.8.4, A.8.31, A.8.32 |
| Audit logging & accountability | CC6.1, CC7.2 | A.8.15, A.8.16 |

---

> Template prepared 2026-06-09. Review with qualified legal/security counsel before relying on it externally.
