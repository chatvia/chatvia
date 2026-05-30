# Teams & roles

Teams are how you collaborate on Chatvia. Each team owns agents, sources, functions, MCP connectors, and a member list. Roles control what each member can do.

![SCREENSHOT — teams list](/screenshots/teams.png)

## Your personal team

On sign-up you get one team automatically. It's yours, you're the owner, and most of the dashboard implicitly works inside it.

You can:

- Stay on the personal team if you're a solo user.
- Invite teammates to it.
- Create additional teams to silo work (one per client, one per environment, etc.).

The team switcher in the top nav toggles between teams.

## Inviting members

In **Settings → Teams → {team}**, click **Invite member**:

- **Email** — Chatvia sends an invite. The recipient signs in (or signs up) and lands on this team.
- **Role** — what they're allowed to do once they accept.

Pending invitations can be **cancelled** before they're accepted. Once accepted, the member appears in the team list.

## Removing members

Open a team, click the **⋯** next to a member, then **Remove**. They lose access immediately. Their existing conversations and content stay attached to the team.

You can't remove the team owner. Transfer ownership (support-assisted) first, or delete the team.

## Roles

A **role** is a named permission set. Built-in roles cover the common cases:

- **Owner** — full access, including billing and member management.
- **Editor** — manage all agents, sources, connectors, functions.
- **Viewer** — read-only.

For finer-grained control, create a **custom role**:

![SCREENSHOT — team role builder](/screenshots/team-roles.png)

The role builder lets you grant:

- **Specific agents** (or all agents).
- **Specific actions** on those agents (read, write, delete).

Example: "Support Engineer" — write access to the Support and Docs agents, read-only on everything else.

## Assigning roles

From a team, edit a member to change their role. From a role, you can also add members in bulk — useful when you create a new role and want to assign it to a dozen people.

## Permissions matrix

| Action | Owner | Editor | Custom (per-agent) | Viewer |
| --- | --- | --- | --- | --- |
| Create agent | ✓ | — | — | — |
| Edit agent settings | ✓ | ✓ | ✓ (if granted) | — |
| Manage sources | ✓ | ✓ | ✓ (if granted) | — |
| View Chat logs | ✓ | ✓ | ✓ (read) | ✓ |
| View Analytics | ✓ | ✓ | ✓ (read) | ✓ |
| Accept handoff | ✓ | ✓ | ✓ (write) | — |
| Manage functions / connectors (account-level) | ✓ | — | — | — |
| Invite / remove members | ✓ | — | — | — |
| Manage billing | ✓ | — | — | — |

## Multiple teams

You can belong to many teams (as owner of yours and member of others). The team switcher in the top nav swaps the context — agents, settings, billing all scope to the active team.

::: tip Personal-account secrets are shared across teams
Your **signing secret** (for identity verification) and your **API keys** are tied to your *account*, not a team. Switching teams in the dashboard doesn't switch credentials — a single API key covers every team you own.
:::

## Leaving a team

In **Settings → Teams → {team}**, click **Leave team**. You lose access. The team carries on with its remaining members.

You can't leave a team you own — delete it or transfer ownership first.

## Deleting a team

Owners can delete a team from its settings page. This hard-deletes:

- Every agent on the team.
- Every source on those agents.
- Every conversation and lead.
- Every member's membership (their accounts remain).

There's a confirmation step asking you to type the team's name. Irreversible.

## Teams via API

Teams, roles, and memberships are all available in the public API. See the [API reference](https://chatvia.ai/docs/api) under **Teams**.
