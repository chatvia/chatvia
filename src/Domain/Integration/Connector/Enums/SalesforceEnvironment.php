<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Enums;

/**
 * Which kind of Salesforce org a workspace connects: a live org or a sandbox.
 * It decides the generic login host and the MCP URL path segment.
 */
enum SalesforceEnvironment: string
{
    case Production = 'production';
    case Sandbox = 'sandbox';
}
