# Chatvia — Security & Compliance Plan (Bootstrapped, Free-First)

_Owner: Founder · Last reviewed: 2026-06-09 · Status: Working plan, not legal advice. Have the DPA template reviewed by a lawyer once before publishing._

---

## TL;DR / Recommendation

1. **Do the free work now.** Fix the 5 zero-cost code gaps (CI test gate, Dependabot, MFA enforcement, Postgres SSL, session encryption), write the ~13-doc policy set + GDPR-processor artifacts (DPA, sub-processor page, RoPA), and ship a hand-built Trust Center page. Cost: **$0**, ~3–4 founder-weeks spread over a quarter.
2. **Pick SOC 2, lead with Type I.** Your pipeline skews US-SMB; US buyers ask for SOC 2 first. ISO 27001 becomes a cheap second cert (~80% evidence overlap) only when an EU/enterprise deal demands the certificate.
3. **Defer the spend.** Don't buy an automation platform or auditor until a real deal is blocked. Then: annual pentest (~$2.5–10k) + SOC 2 Type I (~$5–10k boutique) + a platform only once you enter the Type II window.
4. **Headline cost:** Year-one realistic all-in **$15k–$30k** if a deal forces the audit; **~$0–500** if no deal forces it yet (just the free posture + maybe a DPA legal review).

---

## Decision: which certification, and why

| Option | Fit for Chatvia | Verdict |
|---|---|---|
| **SOC 2 Type I** (point-in-time) | Fast, cheap, satisfies the first US prospect that asks. ~4–8 wks, ~$5–10k boutique CPA. | **Do FIRST when a deal asks.** Start the Type II clock the same day. |
| **SOC 2 Type II** (3–12mo window) | What US enterprises ultimately want. ~$7.5–18k boutique (Security-only). 3-mo minimum observation can't be shortened. | **Defer** until a deal pays for it. |
| **ISO 27001:2022** | EU/UK/global + NIS2-adjacent buyers. ~$5–15k Stage 1+2 for a <50-person scope, + $3–10k/yr surveillance. Certified by an **accredited registrar (UKAS/ANAB)**, not a CPA. | **Second cert**, deferred — cheap once SOC 2 readiness is done. |
| **GDPR processor posture** (DPA + sub-processor list + TOMs) | The actual deal-unblocker for EU SMBs. They need an Art. 28 DPA, not a certificate. | **Do NOW, free.** Highest EU leverage per dollar. |
| SOC 3 | Public marketing badge derived from SOC 2. | Add later, cheap, only after SOC 2. |
| CSA STAR L2 (€40k+), Cyber Essentials (UK-only), SIG-Lite (paid) | Niche/over-spec for SMB. | **Skip** unless a specific buyer/sector forces it. |

**Pick: SOC 2 (Type I → Type II) as the paid path, ISO 27001 as the deferred second cert, GDPR-processor paperwork done free up front.**
**Why:** Your buyers are US-skewed SMBs who raise SOC 2 early; the readiness work is ~80% reusable for ISO when an EU deal eventually wants the certificate. For the EU side, a signable DPA + sub-processor list + Art. 32 TOMs clears most SMB procurement without any certificate — so the free track covers disproportionately many EU deals. If your *first* blocking contract literally says "ISO 27001 from an accredited body," flip the order; otherwise SOC 2 wins the coin-flip.

---

## Phased Roadmap

### Phase 0 — Free, do-now (founder, ~2–4 wks effort over a quarter, **$0**)

**Code/config fixes (all verified against the repo today):**
- [ ] **Re-enable CI tests** — `.github/workflows/tests.yml` currently has the triggers commented out and runs only `on: workflow_dispatch`. Uncomment the `pull_request`/`push` block. _(CC8.1)_
- [ ] **Gate deploys on tests** — `.github/workflows/deploy.yml` has `# tests:` job and `# needs: tests` commented out; prod ships untested on every `v*` tag. Uncomment both. Bump `actions/checkout@v2` → `@v4`. _(CC8.1)_
- [ ] **Branch protection on `main`** — require PR + passing checks, block direct push (free on GitHub).
- [ ] **Add `.github/dependabot.yml`** (composer, npm, github-actions) — none exists today. Enable GitHub secret scanning + push protection. _(CC7.1)_
- [ ] **Wire `composer ci` into CI** — the script already runs `composer audit`, `npm audit`, pint, larastan, eslint, rector, pest; nothing enforces it. Once tests.yml is live, it runs automatically.
- [ ] **Enforce MFA for staff/admin** — Fortify TOTP + passkeys exist (`config/fortify.php`, `User` is `TwoFactorAuthenticatable`) but are optional. Add a route middleware requiring `two_factor_confirmed_at` for `UserType::Workspace` teammates / admin routes (Telescope/Pulse/Horizon already gate on `isAdmin()`). **Highest-value free IAM control — auditors check it first.** _(CC6.1/CC6.6)_
- [ ] **Postgres `sslmode`** — all four connections in `config/database.php` use `'prefer'`; change to `'require'` so app→DB can't silently fall back to plaintext. _(CC6.7)_
- [ ] **`SESSION_ENCRYPT=true`** in prod — `config/session.php` defaults it to `false`. _(CC6.1)_
- [ ] **Strengthen passwords** — `Password::defaults()` is uncustomized (min 8, no breach check). In a service provider: `Password::defaults(fn () => Password::min(12)->uncompromised())`. _(CC6.1)_
- [ ] **MFA on every vendor console** — GitHub, Hetzner, Scaleway, Stripe, Flare, domain registrar, Google. Free.

**Paperwork (free, the bulk of audit evidence):**
- [ ] Write the **~13 policies** (see Control Checklist) from free templates, tailored to Laravel/Postgres/Hetzner/Scaleway.
- [ ] **Asset/data inventory + risk register** (threats: DB breach, widget XSS, leaked secret, prompt-injection KB exfil, vendor outage, ransomware → likelihood×impact → treatment).
- [ ] **GDPR artifacts:** customer-facing DPA, sub-processor register, Art. 30(2) RoPA, breach-response runbook + register, "no DPO required (yet)" assessment, named `privacy@` contact. (See GDPR track.)
- [ ] **Hand-built Trust Center page** on chatvia.ai: practices, sub-processors, DPA link, TOMs, completed CAIQ (= free CSA STAR L1).
- [ ] **Recurring calendar events** so dated evidence accumulates: quarterly access review, quarterly restore test, annual risk refresh, annual tabletop, annual security training.

### Phase 1 — Cheap, unblock deals (founder, days, **~$0–600**)

- [ ] **Publish the DPA** at `/dpa` (self-serve, accept at signup). One lawyer review pass (~$300–600 quote-only, or free with a vetted template).
- [ ] **Publish `/subprocessors`** — production-path vendors only (Hetzner compute/DB + Scaleway LLM/S3/backups/email first).
- [ ] **Widget AI disclosure** (EU AI Act Art. 50) — "You're chatting with an AI assistant" label + opening message, localized, flips to "a human has joined" on Handoff. _Deadline 2 Aug 2026._
- [ ] **Pre-fill + publish a CAIQ** to pre-empt inbound questionnaires.
- [ ] **Re-add an auth/admin audit log** (see remediation P1).

### Phase 2 — Readiness (founder, 2–4 wks, **~$0–180/mo of tooling**)

- [ ] **Backups + restore test** — add `spatie/laravel-backup` (not installed) to dump the Hetzner-hosted Postgres to a separate Scaleway bucket on a Horizon schedule; RPO per backup schedule (to be finalised) — there is no managed-Postgres PITR/continuous transaction-log recovery; **perform a quarterly restore test and write it down.** Store a sealed off-box copy of `APP_KEY`. _(A.8.13)_
- [ ] **Security event log sink** — Flare/Pulse/Telescope/Nightwatch already installed; add a tamper-evident sink for auth/admin events (Better Stack/Logtail free tier).
- [ ] **ISO spine (if heading to ISO):** Statement of Applicability, risk treatment plan, DPIA for widget PII.
- [ ] **Tabletop exercise** (1×/yr): hypothetical "leaked DB dump" walkthrough — the write-up is the evidence.
- [ ] **Optional managed secrets vault** (Doppler/Infisical free tier).

### Phase 3 — Buy the audit (**$ only when a deal forces it**)

- [ ] **Third-party pentest** of app + embeddable widget (XSS/CSP/cross-origin/PII-leak). ~$2.5–10k (Cobalt/Astra focused web-app ~$2.5–5k). The one vuln-mgmt control you can't self-attest.
- [ ] **SOC 2 Type I** via boutique CPA (~$5–10k); start the Type II observation clock simultaneously.
- [ ] **Compliance automation platform** — buy ONLY when entering the Type II window. Sprinto (~$6–8k, lowest incumbent) or Comp AI/Probo (open-source, self-host ~$10k/yr eng time) or Thoropass/Oneleet if you want platform+audit bundled.
- [ ] **SOC 2 Type II** (~$7.5–18k boutique) when a deal demands it; **ISO 27001** (~$5–15k Stage 1+2 + $3–10k/yr surveillance) when an EU/enterprise deal wants the certificate.

---

## First-2-Weeks Sprint (start Monday)

Highest-leverage, all free:

- [ ] Re-enable `tests.yml` triggers + turn on `main` branch protection.
- [ ] Uncomment `tests`/`needs: tests` in `deploy.yml`; bump `checkout@v2`→`@v4`.
- [ ] Add `.github/dependabot.yml` (composer/npm/actions) + enable secret scanning + push protection.
- [ ] Enforce MFA on Workspace/admin routes via middleware on `two_factor_confirmed_at`.
- [ ] Turn on MFA in every vendor console.
- [ ] `sslmode` → `require` in `config/database.php`; `SESSION_ENCRYPT=true` in prod; `Password::min(12)->uncompromised()`.
- [ ] Confirm the **production storage/email/AI path is EU-region** — `.env.example` ships `AWS_DEFAULT_REGION=us-east-1`; any EU data through a US endpoint is an undocumented transfer with no SCCs.
- [ ] Draft the **Information Security Policy** + **Access Control Policy** (first 2 of 13).
- [ ] Start the **RoPA spreadsheet** (reused 3×: DPA annexes, sub-processor page, risk register).
- [ ] Stand up a `info@chatvia.ai` inbox + a stub Trust Center page.

---

## Cost Table (verified; quote-only marked)

> Every commercial platform below is **quote-only** — none publish list prices. Figures are 2025–26 third-party-reported ranges; always ask for a startup/YC discount and share competing quotes.

| Item | Cost | Notes |
|---|---|---|
| All Phase 0 code/config + policies | **$0** | Founder time only |
| Hand-built Trust Center | **$0** | vs SafeBase $8–15k/yr — don't pay |
| CAIQ self-assessment / CSA STAR L1 | **$0** | Optional "Valid-AI-ted" scoring $595 (skip) |
| DPA legal review (1 pass) | **~$300–600** | quote-only; optional with vetted template |
| `spatie/laravel-backup` + extra bucket | **~$0–15/mo** | Storage cents |
| Log sink (Better Stack/Logtail) | **$0** | Free tier |
| Secrets vault (Doppler/Infisical) | **$0** | Free tier, optional |
| **Annual third-party pentest** | **~$2.5–10k** | Boutique web-app ~$2.5–5k |
| **SOC 2 Type I (boutique CPA)** | **~$5–10k** | quote-only; audit fee only |
| **SOC 2 Type II (boutique CPA, Security-only)** | **~$7.5–18k** | quote-only; 30–50% > Type I |
| **ISO 27001 Stage 1+2** | **~$5–15k** | + **$3–10k/yr** surveillance; accredited registrar |
| Sprinto (lowest incumbent platform) | **~$6–8k/yr** | quote-only; as low as ~$4k w/ startup discount; audit SEPARATE |
| Vanta / Drata / Secureframe / Scytale | **~$7.5–12k/yr** | quote-only; Drata may add $10–25k implementation; audit SEPARATE |
| Comp AI (open-source self-host) | **free license, ~$10k/yr eng time** | Managed Pro ~$12k reportedly bundles audit+pentest (estimate) |
| Probo (open-source, MCP/GraphQL) | **free to start** | Services engagement-based, quote-only |
| Thoropass (platform + audit **bundled**) | **~$12–18k/yr** | quote-only; single-vendor all-in |
| Oneleet (platform + audit + pentest + vCISO **bundled**) | **~$15–30k all-in** | quote-only; bundles a real manual pentest |

**Realistic first-year all-in:**
- **No deal forces it yet:** **~$0–600** (free posture + maybe DPA review).
- **A deal forces SOC 2:** **~$20k–$30k** (Type I + pentest + platform), or **~$15–30k bundled** (Thoropass/Oneleet).
- **ISO 27001 instead/also:** **~$15k–$30k** first year incl. tooling + Stage 1+2.

---

## Control Checklist — SOC 2 CC + ISO 27001:2022 Annex A

| Control area | SOC 2 / ISO ref | Free-DIY or Paid |
|---|---|---|
| Information Security Policy (top-level, approved) | CC1.x / ISO Cl.5.2 | **Free** |
| Access Control + enforced MFA + least privilege + access reviews | CC6.1/6.2/6.3/6.6 / A.5.15–5.18 | **Free** |
| Change Management (PR + CI + branch protection + deploy gate) | CC8.1 / A.8.25–8.32 | **Free** |
| Cryptography + Key Management | CC6.1/6.7 / A.8.24 | **Free** (config) |
| Data Classification & Handling | — / A.5.12–5.13 | **Free** |
| Data Retention & Deletion (incl. GDPR erasure) | — / A.5.34 | **Free** (mostly built) |
| Acceptable Use | — / A.5.10 | **Free** |
| Incident Response + breach notification | CC7.3/7.4/7.5 / A.5.24–5.28 | **Free** |
| Business Continuity / Backup & DR | A1 (avail.) / A.8.13, A.5.29–5.30 | **Free–cheap** (~$0–15/mo) |
| Vendor/Sub-processor Management + register | CC9.2 / A.5.19–5.23 | **Free** |
| Risk Management + register + SoA (ISO) | CC3.x/CC4.1/CC9.1 / Cl.6.1 | **Free** |
| HR Security (NDAs, training, on/offboarding) | CC1.1/1.4/1.5 / A.6.1–6.6 | **Free** (bg checks ~$30–100/hire, per-hire only) |
| Vulnerability Mgmt (Dependabot, patch SLA) | CC7.1 / A.8.8 | **Free** (SCA) + **Paid** annual pentest |
| Logging & Monitoring (auth/admin audit log) | CC7.2/7.3 / A.8.15–8.16 | **Free** |
| Security Awareness Training | CC1.4 / A.6.3 | **Free** (self-cert) |
| The audit itself (SOC 2 / ISO cert) | — | **Paid** (Phase 3) |
| GRC automation platform | — | **Paid** (optional, Phase 3) |

**Separation-of-duties (solo founder):** auditors accept a documented compensating control — "sole engineer; all changes via PR + CI + immutable Git history + quarterly self-review." Write it down; the branch-protection/CI evidence backs it.

---

## Chatvia Technical Remediation List (from the gap audit)

| # | Gap | Status (verified) | Fix | File(s) | Effort | Priority |
|---|---|---|---|---|---|---|
| 1 | CI tests disabled | `tests.yml` runs only `on: workflow_dispatch` | Uncomment `pull_request`/`push` triggers | `.github/workflows/tests.yml` | Low | **P0** |
| 2 | Deploy not test-gated | `# tests:` job + `# needs: tests` commented; ships on every `v*` tag | Uncomment both; bump `checkout@v2`→`@v4` | `.github/workflows/deploy.yml` | Low | **P0** |
| 3 | No dependency scanning | No `.github/dependabot.yml`; `composer/npm audit` only in unenforced local `composer ci` | Add Dependabot (composer/npm/actions); enable secret scanning + push protection | `.github/dependabot.yml` (new) | Low | **P0** |
| 4 | MFA optional for staff/admin | Fortify TOTP+passkeys exist but not enforced | Enforce on the planned **Filament** admin panel (built-in `->requiresTwoFactorAuthentication()`); keep tenant-side 2FA optional unless a deal requires forcing it | Filament panel provider (planned) | Low | **P0** |
| 5 | Postgres SSL can downgrade | `sslmode => 'prefer'` ×4 | Change to `'require'` (or `verify-full` w/ the Hetzner Postgres CA) | `config/database.php` | Low | **P0** |
| 6 | Session payload unencrypted | `SESSION_ENCRYPT` defaults `false` | Set `SESSION_ENCRYPT=true` in prod | `config/session.php` / prod `.env` | Low | **P0** |
| 7 | Weak password policy | `Password::defaults()` uncustomized (min 8, no breach check) | `Password::min(12)->uncompromised()` in a provider boot | `src/App/Providers/AppServiceProvider.php` (or FortifyServiceProvider) | Low | **P0** |
| 8 | No backups/DR codified | No `spatie/laravel-backup`, no `config/backup.php`, no scheduled dump | Add package → dump the Hetzner-hosted Postgres to a separate Scaleway bucket on Horizon schedule (dump-based, RPO per backup schedule TBD — no managed PITR); **quarterly restore test**; seal `APP_KEY` off-box | `composer.json`, `config/backup.php` (new), scheduler | Medium | **P1** |
| 9 | No auth/admin audit log | `agent_activity` audits tool-calls only; `spatie/activitylog` removed | Re-add a thin auth/admin event log (logins/failures, MFA/password changes, role grants, API-key issuance, data exports) via Fortify/auth event listeners → dedicated channel | Listeners + `DomainServiceProvider::registerEventListeners()` (manual registration — no auto-discovery) | Medium | **P1** |
| 10 | Lead PII plaintext | `Lead.php` casts only `contacted_at`; `ip_address`/`email`/`user_agent` plaintext | Encrypt or drop Lead PII; HMAC blind-index for email lookups; trim IP/user_agent retention | `src/Domain/Lead/Models/Lead.php` | Medium | **P1** |
| 11 | EU data residency risk | `.env.example` `AWS_DEFAULT_REGION=us-east-1`; AWS S3 disk alongside Scaleway | Confirm prod storage/email/AI path is EU-region; honor `users.data_region='eu'` end-to-end; SCCs/DPF + TIA for any non-EU vendor | `.env.example`, `config/filesystems.php`, `User.php` | Medium | **P1** |
| 12 | Tenant isolation relies on per-request checks | Policy/relationship scoping, no global scope; one forgotten `cannot()` leaks cross-tenant | Add Architecture test asserting controller `authorize`; optionally global scope on `Lead`/`Conversation` | `tests/Architecture/`, model scopes | Medium | **P2** |
| 13 | Per-visitor erasure path undocumented | Workspace-level export + nightly prune exist; no per-data-subject delete/export | Build controller-facing "erase this visitor/lead/conversation" action (hard-delete Lead + conversations + S3 attachments + derived chunks/embeddings) + per-subject export | New action in `src/Domain/Lead/Actions/`, conversation actions | Medium | **P2** |
| 14 | Sanctum token TTL 1 year | `expiration` = 525600 min | Acceptable; document key-rotation runbook | `config/sanctum.php` | Low | **P2** |

_Already strong (capture as evidence, no work): CSP/HSTS/nosniff/X-Frame-Options (`ContentSecurityPolicy.php`, deliberate `unsafe-inline` — leave as-is), per-route rate limiting, secrets not in Git, retention prune jobs + DataExport subsystem, `agent_activity` immutable tool-call audit, admin gates on Telescope/Pulse/Horizon._

---

## GDPR / EU Track (concrete to-do)

You are a **processor**; your customers are **controllers**. They own consent and lawful basis — **do not collect the visitor's GDPR consent yourself.** Your job: be a contractable, compliant processor and give customers the hooks + disclosures.

- [ ] **Publish a self-serve DPA** at `/dpa` covering all eight Art. 28(3) clauses (documented instructions, confidentiality, Art. 32 security, sub-processor conditions, assist with data-subject rights, assist with breach/DPIA, delete-or-return, audit rights). Use **general sub-processor authorization** + a published list with **30-day advance-notice/objection right**. Back clauses (e)/(g) with existing DataExport + PruneExpired* jobs. One lawyer review before go-live.
- [ ] **Publish `/subprocessors`** — production-path only, each with purpose + location: **Hetzner** (compute/application servers + the PostgreSQL database + Redis/queues — EU/Germany) and **Scaleway** (LLM inference of chat content + S3 storage of attachments/exports + database backups + transactional email — EU/France) first, the headline residency selling point; then only-when-enabled vendors (Stripe/billing, Flare, Cloudflare Turnstile, BunnyCDN, any active embedding/parse vendor). Link Scaleway's own sub-processor list. Don't over-disclose dormant config.
- [ ] **Sub-processor DPAs** — sign each vendor's click-through DPA; confirm **Scaleway business terms exclude using inference data for model training** and record it.
- [ ] **Widget consent-awareness (not your own banner)** — gate all non-essential device storage + lead capture behind the host page's consent signal/CMP; keep only strictly-necessary session storage ungated (ePrivacy exemption). Expose a documented JS API (e.g. `chatvia('consent', true/false)`) + init flag. Document exactly what the widget stores so customers can list it in their cookie policy. (EDPB reads Art. 5(3) to cover localStorage, not just cookies.)
- [ ] **EU AI Act Art. 50 disclosure (deadline 2 Aug 2026)** — persistent visible "You're chatting with an AI assistant" label + opening message, **localized** (mind the widget `t()` no-fallback footgun — missing keys render raw; add to `languages/*.json`). Flip to "a human has joined" on Handoff. Write a short "is it obvious?" assessment either way (burden of proof is on you).
- [ ] **Per-data-subject erasure + export** — build the controller-facing flow (remediation #13). Confirm soft-deletes are **hard-purged within a stated window** (an Art. 17 erasure must actually remove data). Trim raw IP/user_agent retention on Leads.
- [ ] **Data residency** — confirm prod storage/email/AI is EU-region (the `us-east-1` default is a red flag); honor `data_region='eu'` end-to-end; advertise EU hosting (Hetzner/Germany for compute & database, Scaleway/France for AI inference, storage & backups); SCCs/DPF + 1-page TIA for any unavoidable non-EU vendor.
- [ ] **Art. 30(2) RoPA** (one spreadsheet) — categories of processing per controller, sub-processors, transfer mechanisms, retention windows (90/180/366 days per `config/billing.php`), Art. 32 measures. Reuse as DPA annexes + sub-processor page. (The <250-employee exemption almost certainly doesn't apply — processing isn't occasional and free-text chat can carry special-category data.)
- [ ] **Breach workflow** — runbook (detect → assess → **notify affected customers without undue delay, e.g. 48–72h** so they hit their Art. 33 72h regulator deadline) + breach register (Art. 33(5)) + a DPA clause stating the notice commitment + a known contact channel per customer.
- [ ] **Governance docs** — named `privacy@` contact; a 3-line documented "no DPO required (yet)" assessment (Art. 37 — likely not "large-scale monitoring as core activity"); assess whether an **Art. 27 EU representative** is needed if you have no EU establishment and offer to EU data subjects (likely yes).

---

## "Buy the Audit" Trigger Signals

Start spending the moment **any** of these fires — not before:

1. **A prospect's contract or security questionnaire explicitly requires "SOC 2 Type II report" or "ISO 27001 certification from an accredited body"** as a gating condition (not a nice-to-have).
2. **A deal is verbally committed / in late-stage procurement** and the *only* remaining blocker is the certificate — the deal value covers the audit several times over.
3. **The exact wording decides the order:** "SOC 2 Type II" → SOC 2 first; "ISO 27001 from an accredited body" → ISO first.
4. **You're losing >1 deal per quarter** to a missing artifact (43% of vendors report a missing compliance artifact directly delayed/killed a deal).
5. **A buyer demands a pentest report** specifically → book the pentest first (it's a prerequisite line item for the audit anyway and useful standalone).
6. **You're entering a Type II window** → that's the trigger to buy a GRC automation platform (it pays for itself in saved evidence-collection labor), not before.

**Until a trigger fires:** keep doing the free Phase 0–2 work so that when a deal lands, you're weeks from a Type I, not months from zero.
