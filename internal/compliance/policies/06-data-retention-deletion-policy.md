# Data Retention & Deletion Policy

| | |
|---|---|
| **Owner** | Founder / Security Lead |
| **Version** | 1.0 |
| **Effective date** | 2026-06-09 |
| **Review cadence** | Annual (or on material change) |
| **Classification** | Internal |

## 1. Purpose

This policy defines how long Chatvia retains personal and customer data, how that data is deleted when it is no longer required, and how Chatvia responds to data-subject erasure and end-of-contract requests. It exists to satisfy GDPR storage-limitation (Art. 5(1)(e)), erasure (Art. 17), and data-portability (Art. 20) obligations, and to give workspaces the contractual deletion/return guarantees expected of a processor.

## 2. Scope

This policy covers all data Chatvia stores on behalf of its customers and the visitors who interact with their AI agents:

- Conversation transcripts and messages
- Leads (visitor contact PII captured from conversations)
- Uploaded knowledge-base sources (files, websites, text) and their vector embeddings
- Chat attachments (visitor uploads)
- Tool-call audit records (`agent_activity`)
- Customer account, billing, API-key, and OAuth-token records

Chatvia operates as a **data processor**; each customer workspace is the **controller** and sets the lawful basis and instructions for the data it loads. Retention windows below are Chatvia's processor defaults; a controller may instruct shorter retention or earlier erasure at any time.

## 3. Retention principle

Chatvia shall retain personal data only as long as needed to deliver the service, meet the customer's plan entitlement, or satisfy a legal obligation. Retention is **plan-driven and time-bounded by default**, not indefinite. Where no business or legal need remains, data shall be deleted.

## 4. Retention windows

Conversation history retention is set per billing plan and is the contractual horizon shown to the customer (`config/billing.php`):

| Plan | Conversation-history retention |
|---|---|
| Starter | 90 days |
| Standard | 180 days |
| Pro | 366 days |

Mechanics:

- Each conversation carries an `expires_at` horizon derived from the owning workspace's current plan window.
- Retention is **activity-based**: an active conversation renews. `Conversation::extendRetentionIfDueSoon()` pushes `expires_at` out by a fresh plan window when a conversation is used within 3 days of its horizon, so live threads are not pruned mid-use while dormant ones still age out.
- A plan downgrade shortens the effective window on the next prune pass; an upgrade lengthens it.

Other data classes:

| Data class | Retention default | Mechanism |
|---|---|---|
| Chat attachments (Scaleway public bucket) | 30 days | `app:prune-chat-attachments --days=30` |
| Tool-call audit (`agent_activity`) | `chatvia.governance.retention_days` (default 90 days) | `app:prune-agent-activity` |
| Data-export ZIP archives | 24-hour download window | `app:prune-expired-data-exports` |
| Telescope debug data | 24 hours | `telescope:prune --hours=24` |
| Account, billing, knowledge sources, Leads | Life of the contract, then §8 | Deleted on account closure / erasure |

## 5. Prune jobs (enforcement)

Retention is enforced by scheduled console commands, not manual cleanup. They run nightly under the Horizon scheduler (`routes/console.php`, `bootstrap/app.php`), sequenced so passes do not contend:

| Time (UTC) | Command | Effect |
|---|---|---|
| 00:15 | `app:prune-expired-data-exports` | Deletes export ZIPs past their 24h window and Failed exports older than 7 days. |
| 03:00 | `app:prune-chat-attachments` | Hard-deletes attachment objects older than the window from the Scaleway bucket. |
| 03:30 | `app:prune-expired-conversations` | Soft-deletes conversations past `expires_at`; cascades to messages, attachments, and S3 objects via Eloquent. |
| 03:45 | `app:prune-tool-approvals` | Clears expired tool-approval checkpoints. |
| 04:00 | `app:prune-agent-activity` | Deletes audit rows older than the governance retention window. |

Each command operates in bounded batches (`chunkById`) so a large prune cannot exhaust connections or block the queue. Prune outcomes are observable via Horizon and Flare; a failed schedule surfaces as an alert.

## 6. Customer data export (portability)

Workspaces can self-serve a full export of their data (Settings → Data Export), satisfying the controller's Art. 20 portability needs:

- `RequestDataExportAction` enforces **one active export per user** and dispatches `PrepareDataExportJob`.
- `BuildDataExportArchiveAction` assembles a ZIP containing `profile.json`, `agents.json`, `knowledge-sources.json`, `conversations.json`, `messages.json`, `teams.json`, `billing.json`, `api-keys.json` (metadata only — secrets are never exported), plus conversation attachments. A `README` manifest describes each file.
- The archive is written to Scaleway storage, the requester is notified (`DataExportReady`), and the ZIP **expires 24 hours after generation** and is then hard-deleted by the prune job. Download is gated by a 64-character single-use token.

## 7. Per-data-subject erasure (GDPR Art. 17)

Erasure follows the processor/controller split:

- **Visitor / Lead erasure** is actioned by the **controller** (the workspace), who holds the lawful basis and the relationship with the data subject. The workspace deletes the Lead and/or the source conversation from its dashboard; the delete propagates to messages, attachments, and the associated embeddings. Where a controller instructs Chatvia to action an erasure on its behalf, Chatvia shall execute it without undue delay and confirm completion to `info@chatvia.ai`.
- **Workspace account erasure** is self-served via account closure (`DeleteAccountController`), which removes the account and triggers deletion of owned agents, knowledge sources, conversations, Leads, and credentials.
- Erasure removes data from the **primary store**. Backups (§9) are not selectively edited; erased data ages out of backups within the backup retention cycle and is overwritten. This residual-in-backup window is the documented, GDPR-recognised limit of erasure and is disclosed to controllers.
- Erasure requests to Chatvia shall be acknowledged within 5 business days and completed within 30 days, consistent with GDPR timelines.

## 8. Hard-purge of soft-deletes

Several records soft-delete first (e.g. conversations set `deleted_at`) so an accidental deletion is recoverable for a short grace period. Soft-deleted records are **not retained indefinitely**:

- Soft-deleted conversations and their cascaded child rows shall be **hard-purged within 30 days** of soft-deletion.
- Associated S3 attachment objects are removed at soft-delete time by the cascade, so visitor file content does not survive the grace period even while the row does.
- The hard-purge pass runs as part of the nightly prune sequence; no soft-deleted personal data persists beyond the grace window.

## 9. Backups & residual data

- Backups are dump-based, taken to a **separate Scaleway bucket** (spatie/laravel-backup, planned) from the Hetzner-hosted PostgreSQL on a Horizon schedule; recovery point is per backup schedule (to be finalised). A quarterly restore test verifies recoverability.
- Backups inherit a defined rolling retention; erased or pruned data is purged from backups as the cycle rotates. Backups are encrypted at rest and access is restricted to the Founder.

## 10. End-of-contract delete / return

On termination, cancellation, or non-renewal:

- The workspace retains **30 days** of self-service access to request a full export (§6) before deletion.
- After the 30-day window, Chatvia shall **delete all customer personal data** from primary stores — conversations, messages, Leads, knowledge sources, embeddings, and attachments — within a further 30 days, and shall purge it from backups on the normal backup-rotation cycle.
- Chatvia shall confirm deletion in writing to the controller on request (`info@chatvia.ai`).
- Aggregated, fully anonymised usage statistics that contain no personal data may be retained for capacity planning. Billing/invoice records are retained only as long as required by Denmark tax/accounting law, then deleted.

## 11. Roles & responsibilities

The Founder / Security Lead owns this policy, the prune schedule, and erasure handling. **Compensating control for limited segregation of duties (sole engineer):** all changes to retention windows, prune commands, or deletion logic ship via GitHub PR with CI (Pest, Larastan, `composer audit`) and `main` branch protection, leaving an immutable Git history; prune execution and failures are independently observable in Horizon and Flare; the schedule and config are reviewed at least annually. Privacy enquiries: `info@chatvia.ai`. Security enquiries: `info@chatvia.ai`. Operating entity: Akkadian, Skovbrynet 50, 1 tv, Næstved, Denmark, governed by the laws of Denmark.

## 12. Review

Reviewed at least annually or on material change (new data class, plan change, schedule change, regulatory change). Changes are version-controlled with this document.

## 13. Mapping

| Requirement | SOC 2 Common Criteria | ISO 27001:2022 Annex A |
|---|---|---|
| Retention windows & storage limitation | CC6.5, CC6.7 | A.5.33, A.8.10 |
| Scheduled deletion / prune enforcement | CC6.5 | A.8.10, A.8.12 |
| Erasure & end-of-contract return/delete | CC6.5, P4.0 (Privacy) | A.5.33, A.5.34 |
| Data export / portability | CC6.1, P5.0 (Privacy) | A.5.34 |
| Backups & residual-data handling | A1.2, CC6.7 | A.8.13, A.8.10 |
| Change control over retention logic | CC8.1 | A.8.32 |
| Monitoring of deletion jobs | CC7.2 | A.8.15, A.8.16 |

> Template prepared 2026-06-09. Review with qualified legal/security counsel before relying on it externally.
