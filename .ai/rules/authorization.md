---
paths:
  - 'src/**/Policies/**'
  - 'src/App/Http/Middleware/**'
  - 'src/App/Http/Controllers/**'
  - 'routes/**'
---

# Authorization

## Another workspace's record is a 404
A record from another workspace answers 404, never 403; 403 is only for a teammate who lacks a permission inside the workspace. Every dashboard and API route group has `workspace.scope`; `can.access.agent` also 404s a record named next to `{agent}` that is not the agent's.

## Put permission checks on the route
Gate routes with middleware: `can.access.agent`, `agent.permission:…`, `team.permission:…`, `api.owner`. Don't use Laravel's `->can()` route middleware, and don't check permissions in the controller body.

## Check permissions, not roles
Ask for a named permission (`hasTeamPermission()`, the middleware); never compare role names. The owner bypass is `ownsTeam()` or `isWorkspaceOwner()`. The permission catalogue is `config/team-permissions.php`. The frontend uses `useTeamPermissions().hasPermission()` for display only; the server always decides.

## Record-level checks pick the status on purpose
When a check depends on the record and no middleware covers it, ask the policy and choose the status: `abort_unless($request->user()->can('update', $model), 404)` at a workspace boundary, 403 for a missing permission.

## Policies sit next to their model
Write `{Context}/Policies/{Model}Policy` with `viewAny`, `view`, `create`, `update`, `delete` plus custom verbs. `AuthServiceProvider` finds policies by name; don't add `Gate::policy()` or `#[UsePolicy]`. Use a Gate only for a check that is not about a model.

## No admin override
Never add `Gate::before()` or a policy `before()` that lets platform admins through. Admins follow workspace rules; their own access is the Filament panel's `canAccessPanel()`.

## API tokens carry no abilities
The API applies the dashboard permission model to the token's user. Don't scope tokens with abilities or check `tokenCan()`.

## No denial logging
Never log who was refused together with the resource they probed at an auth boundary, not even sampled.

## Prove both statuses in tests
Every protected route has a test: a teammate without the permission gets 403, another workspace's record gets 404. `tests/Feature/App/Http/CrossWorkspaceRouteTest.php` sweeps every route for the 404, and `WorkspacePermissionRouteTest.php` next to it (with `Api/V1/ApiPermissionTest.php` for the API) for the 403.
