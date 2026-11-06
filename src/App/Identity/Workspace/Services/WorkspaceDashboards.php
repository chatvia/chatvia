<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Services;

use App\Identity\Workspace\Models\Workspace;
use Spectra\Enums\DashboardSection;
use Spectra\Enums\DashboardWidget;
use Spectra\Facades\Spectra;
use Spectra\Models\SpectraDashboard;

/**
 * A workspace's own page in Spectra: a read-only dashboard of the AI calls made
 * for that workspace and nothing else. Spectra's main screens show every
 * workspace's calls and stay with staff; this is what a workspace's owner gets.
 * An admin makes it on the workspace's page in the admin panel.
 */
class WorkspaceDashboards
{
    /** The name and the workspace identify a dashboard, so making it again keeps its link. */
    private const string NAME = 'AI usage';

    /** The live dashboard of the workspace, or null when it has none. */
    public function for(Workspace $workspace): ?SpectraDashboard
    {
        return Spectra::dashboard()->list($workspace)->firstWhere('is_active', true);
    }

    /**
     * @param  list<DashboardSection|string>  $sections  the pages it links to; costs also show spend in its widgets
     * @param  bool  $showMessages  let the owner read the messages and replies
     */
    public function create(Workspace $workspace, array $sections, bool $showMessages): SpectraDashboard
    {
        return Spectra::dashboard()->create(
            self::NAME,
            $workspace,
            widgets: [
                DashboardWidget::Stats,
                DashboardWidget::RequestsTrend,
                DashboardWidget::CostTrend,
                ['type' => DashboardWidget::UsageByModel, 'size' => 'half'],
                ['type' => DashboardWidget::UsageByProvider, 'size' => 'half'],
                DashboardWidget::RecentRequests,
            ],
            sections: $sections,
            showMessages: $showMessages,
        );
    }

    /** Ends the dashboard's link. The calls it showed stay until Spectra's retention prunes them. */
    public function delete(Workspace $workspace): void
    {
        Spectra::dashboard()->list($workspace)->each(fn (SpectraDashboard $dashboard): bool => Spectra::dashboard()->delete($dashboard));
    }
}
