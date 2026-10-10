# Workspaces, teams & roles

Chatvia is organised around a **workspace** — the company account that owns your agents, sources, MCP connectors, functions, and people. Inside a workspace, **teams** group your people by scope-of-access. Roles control what each member can do.

![SCREENSHOT — teams list](/screenshots/teams.png)

## Workspace vs team

- **Workspace** — the customer account. One per company. Owns agents, sources, secrets, and the full member list. It can have a [monthly message limit](/guide/account/message-limits).
- **Team** — a scope-of-access grouping inside the workspace. Teams let you silo work (one per client, one per product area, one per environment) without spinning up a new workspace.

The workspace switcher in the top nav toggles between any workspaces you belong to. Within a workspace, the team selector picks which team you're acting on.

## Two kinds of people in a workspace

A workspace has two kinds of members, and they live in different places:

- **Teammates** — people who *manage* agents from the dashboard. Invited from **Settings → Teams**. Roles control what they can do.
- **Chatters** — end-users who *talk* to agents from the unified `/chat` surface. They never see the dashboard. Invited from **Settings → Members** (workspace-wide) or from an agent's **Members** tab (per-agent grant).

Keep this split in mind for the rest of the page: when we say "invite a member" we mean a teammate. Chatters are covered in their own sub-section.

## Inviting teammates

In **Settings → Teams → {team}**, click **Invite member**:

- **Email** — Chatvia sends an invite. The recipient signs in (or signs up) and lands on this team inside your workspace.
- **Role** — what they're allowed to do once they accept.

Pending invitations can be **cancelled** before they're accepted. Once accepted, the teammate appears in the team list.

## Inviting chatters

Chatters are end-users you want to give agent access to. They never reach the dashboard.

- **Workspace-wide invite** — **Settings → Members → Invite chatter**. Pick which agents the chatter should have access to.
- **Per-agent invite** — open an agent → **Members** tab → **Invite chatter**. The grant is scoped to that one agent.

Once accepted, the chatter logs into `/chat` and can switch between every agent they've been granted access to. Revoke access from the same screens.

## Removing teammates

Open a team, click the **⋯** next to a teammate, then **Remove**. They lose dashboard access immediately. Their existing conversations and content stay attached to the workspace.

You can't remove the workspace owner. Transfer ownership (support-assisted) first, or delete the workspace.

## Roles

A **role** is a named permission set. Built-in roles cover the common cases:

- **Owner** — full access, including connectors and member management.
- **Editor** — manage all agents, sources, connectors, functions.
- **Viewer** — read-only.

For finer-grained control, create a **custom role**:

![SCREENSHOT — team role builder](/screenshots/team-roles.png)

The role builder lets you grant:

- **Specific agents** (or all agents).
- **Specific actions** on those agents (read, write, delete).

Example: "Support Engineer" — write access to the Support and Docs agents, read-only on everything else.

## Assigning roles

From a team, edit a teammate to change their role. From a role, you can also add teammates in bulk — useful when you create a new role and want to assign it to a dozen people.

## Permissions matrix

| Action | Owner | Editor | Custom (per-agent) | Viewer |
| --- | --- | --- | --- | --- |
| Create agent | ✓ | — | — | — |
| Edit agent settings | ✓ | ✓ | ✓ (if granted) | — |
| Manage sources | ✓ | ✓ | ✓ (if granted) | — |
| View Chat logs | ✓ | ✓ | ✓ (read) | ✓ |
| View Analytics | ✓ | ✓ | ✓ (read) | ✓ |
| Accept handoff | ✓ | ✓ | ✓ (write) | — |
| Resolve tool-approval checkpoints | ✓ | ✓ | ✓ (write) | — |
| Grant chatter access (workspace-wide or per-agent) | ✓ | ✓ | — | — |
| Set a chatter's monthly message limit | ✓ | ✓ | — | — |
| Manage Connectors / Functions (workspace-level) | ✓ | — | — | — |
| Invite / remove teammates | ✓ | — | — | — |

## Multiple teams

You can belong to many teams inside a workspace (and to several workspaces). The team selector swaps the context — agents, settings, and member lists all scope to the active team. Connectors and the workspace's message limit stay at the workspace level.

::: tip Workspace secrets are shared across teams
Your **signing secret** (for identity verification) and your **API keys** are tied to the *workspace*, not a team. Switching teams in the dashboard doesn't switch credentials — a single API key covers every team in the workspace.
:::

## Leaving a team

In **Settings → Teams → {team}**, click **Leave team**. You lose access to that team. The team carries on with its remaining members, and you stay in the workspace if you belong to other teams.

You can't leave a team you own — delete it or transfer ownership first.

## Deleting a team

Owners can delete a team from its settings page. This hard-deletes:

- Every agent on the team.
- Every source on those agents.
- Every conversation and lead.
- Every teammate's membership on that team (their accounts remain).
- Every chatter grant scoped to those agents.

There's a confirmation step asking you to type the team's name. Irreversible.

## Teams via API

Workspaces, teams, roles, and memberships are all available in the public API. See the [API reference](https://chatvia.ai/docs/api) under **Teams**.
