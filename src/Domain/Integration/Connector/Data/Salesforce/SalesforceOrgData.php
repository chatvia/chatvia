<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Data\Salesforce;

use Domain\Integration\Connector\Enums\SalesforceEnvironment;

/**
 * What a workspace tells us about its Salesforce org to connect the hosted
 * MCP server: its External Client App credentials, the environment, the
 * optional My Domain login URL and which hosted MCP server to use.
 */
final readonly class SalesforceOrgData
{
    public function __construct(
        public string $clientId,
        public ?string $clientSecret,
        public SalesforceEnvironment $environment,
        public ?string $myDomain,
        public string $server,
    ) {}
}
