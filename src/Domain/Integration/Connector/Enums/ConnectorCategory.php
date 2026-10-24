<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Enums;

/**
 * The group a catalog connector is listed under on the Connectors page. The
 * value is the English label the dashboard shows when it has no translation;
 * the dashboard translates it by `connectors.categories.{snake_case label}`.
 */
enum ConnectorCategory: string
{
    case Analytics = 'Analytics';
    case Automation = 'Automation';
    case Communication = 'Communication';
    case DeveloperTools = 'Developer Tools';
    case Marketing = 'Marketing';
    case Payments = 'Payments';
    case Productivity = 'Productivity';
    case Sales = 'Sales';
    case Support = 'Support';
    case Utilities = 'Utilities';
}
