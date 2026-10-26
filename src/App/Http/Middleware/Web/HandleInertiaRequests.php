<?php

namespace App\Http\Middleware\Web;

use App\Http\Auth\BotCheck;
use App\Http\Middleware\Chat\ChatHandleInertiaRequests;
use App\Identity\Workspace\Enums\SocialProvider;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Services\AgentUrlBuilder;
use Domain\Knowledge\Source\File\Enums\KnowledgeFileType;
use Domain\Membership\Team\Services\TeamService;
use Domain\Notification\Data\NotificationSummaryData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Middleware;
use Support\License\Entitlements;

/**
 * Inertia root for the dashboard and the rest of the `web` group: the props
 * every page shares — the signed-in user, flash messages, locale, current
 * team and its permissions, and feature flags.
 * The hosted chat has its own root middleware
 * ({@see ChatHandleInertiaRequests}).
 */
class HandleInertiaRequests extends Middleware
{
    #[\Override]
    protected $rootView = 'app';

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function share(Request $request): array
    {
        $user = $request->user('web');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            // The Cloudflare Turnstile bot check of the public forms (Settings →
            // Security): its site key and the forms it protects, or null.
            'turnstile' => fn (): ?array => app(BotCheck::class)->forPages(),
            // The sign-in providers whose OAuth app is set up (Settings →
            // Sign-in): only their buttons are shown.
            'socialProviders' => fn (): array => array_map(fn (SocialProvider $provider): string => $provider->value, SocialProvider::configured()),
            'auth' => [
                'user' => $user,
                // Owner-only areas (workspace, teams) are hidden from teammates;
                // the routes enforce the same rule (DenyIfTeammate).
                'is_workspace_owner' => $user?->isWorkspaceOwner() ?? false,
            ],
            // The matched route's name and raw parameters, for active-state
            // checks (useCurrentRoute); URLs themselves come from Wayfinder.
            'route' => [
                'name' => $request->route()?->getName(),
                // An object even when empty, so the page gets {} rather than [].
                'params' => (object) ($request->route()?->originalParameters() ?? []),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
                // One-shot payloads flashed by a controller — e.g. the signed
                // accept URL after an agent-member invitation is sent.
                'invitation' => $request->session()->get('invitation'),
            ],
            // Persisted UI language for the signed-in user. `auto` lets the
            // client fall back to the browser locale. app.ts syncs vue-i18n +
            // document dir/lang from this on every Inertia navigation.
            'locale' => $user?->locale ?? 'auto',
            // The install's default language (Settings → Platform), used for
            // `auto` when the browser's language is not one the app has.
            'defaultLocale' => config('app.locale'),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'schema' => $request->getScheme(),
            'environment' => app()->environment(),

            // Where every agent's hosted chat lives (`{chatBaseUrl}/{slug}`),
            // for the address fields of the agent settings.
            'chatBaseUrl' => AgentUrlBuilder::chatBaseUrl(),
            // On a hosted chat page: its `/chat/{agentSlug}` segment, so the
            // frontend's route helpers fill it in (setUrlDefaults). Lazy: the
            // chat's agent is resolved after this middleware runs.
            'agentSlug' => fn (): ?string => URL::getDefaultParameters()['agentSlug'] ?? null,
            // The session's CSRF token. Chat calls send it as X-CSRF-TOKEN: on
            // a /chat page the browser holds two XSRF-TOKEN cookies (the
            // dashboard's and the chat's), so reading the cookie is ambiguous.
            'csrfToken' => csrf_token(...),
            // The notification bell: the unread count and the person's switches
            // for desktop alerts and sound. Live updates come over Reverb.
            'notifications' => fn (): ?NotificationSummaryData => $user instanceof User ? new NotificationSummaryData(
                unread: $user->userNotifications()->unread()->count(),
                preferences: $user->notificationPreferences(),
            ) : null,
            // Lazy, like the props above: a partial reload that does not ask
            // for them skips the team and permission queries.
            'permissions' => fn (): array => $this->getTeamPermissions($request),
            'currentTeam' => fn (): ?array => $user ? $this->getCurrentTeam($request) : null,
            // Install-wide switches for unfinished features (`chatvia.features`).
            // Frontend gates via `useFeature().hasFeature('youtube_sources')`.
            'features' => $user ? [
                'youtube_sources' => (bool) config('chatvia.features.youtube_sources', false),
            ] : [],
            // Whether the owner may download their data and delete their
            // account from the Account area (Settings → Workspaces → Owner
            // account); the routes answer 404 while a switch is off.
            'accountOptions' => $user instanceof User ? [
                'data_export' => (bool) (config('chatvia.account.data_export') ?? true),
                'deletion' => (bool) (config('chatvia.account.deletion') ?? true),
            ] : null,
            // The dashboard tour: how far this person got in it (`stage`, null =
            // not started), or null while the admin has it off (Settings →
            // Workspaces → Onboarding), which also hides "Take the tour".
            'onboardingTour' => $user instanceof User && (bool) (config('chatvia.onboarding.tour') ?? true) ? [
                'stage' => $user->onboardingTourStage()?->value,
            ] : null,
            // Whether this install has an Enterprise key: the Security and
            // Governance pages lock their Enterprise features without one.
            'enterprise' => $user instanceof User && app(Entitlements::class)->enterprise(),
            // Whether this install holds any license key; without one the
            // free-use limits apply and the pages say what is locked.
            'license' => $user instanceof User ? $this->license(app(Entitlements::class)) : null,
            // The knowledge file types this install accepts (Settings →
            // Ingestion), each with the extensions this server can read, for
            // the upload pages and the sources menu. An object even when empty.
            // Whether websites can be imported as knowledge: the import
            // service is set up (Settings → Knowledge → Web scraper).
            'canImportWebsites' => $user && filled(config('services.firecrawl.key')),
            'knowledgeFileTypes' => $user ? (object) collect(KnowledgeFileType::enabled())
                ->mapWithKeys(fn (KnowledgeFileType $type): array => [$type->value => $type->readableExtensions()])
                ->filter()
                ->all() : (object) [],
        ];
    }

    /**
     * @return array{id: int, external_id: string, name: string, is_owner: bool, is_primary: bool}|null
     */
    private function getCurrentTeam(Request $request): ?array
    {
        $user = $request->user('web');

        if (! $user) {
            return null;
        }

        // Read-only resolve from the DB (no silent UPDATE on Inertia render).
        $team = $user->currentOrPrimaryTeam();

        if (! $team) {
            return null;
        }

        return [
            'id' => $team->id,
            'external_id' => $team->external_id,
            'name' => $team->name,
            'is_owner' => $user->ownsTeam($team),
            'is_primary' => $team->id === $user->primaryTeam()?->id,
        ];
    }

    /**
     * @return array<int, list<string>>
     */
    private function getTeamPermissions(Request $request): array
    {
        $user = $request->user('web');

        if (! $user) {
            return [];
        }

        return app(TeamService::class)->userPermissionsMap($user);
    }

    /**
     * @return array{licensed: bool, buyUrl: string, limits: array{workspaces: ?int, agents: ?int, messages: ?int}}
     */
    private function license(Entitlements $entitlements): array
    {
        return [
            'licensed' => $entitlements->licensed(),
            'buyUrl' => (string) config('chatvia.license.buy_url'),
            'limits' => [
                'workspaces' => $entitlements->workspaceLimit(),
                'agents' => $entitlements->agentLimit(),
                'messages' => $entitlements->monthlyMessageLimit(),
            ],
        ];
    }
}
