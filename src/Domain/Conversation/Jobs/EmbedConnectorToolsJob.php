<?php

declare(strict_types=1);

namespace Domain\Conversation\Jobs;

/**
 * @deprecated Moved to \Domain\Integration\Tool\Jobs\EmbedConnectorToolsJob; kept so jobs queued before the deploy still unserialize. Remove after the next release.
 */
final class EmbedConnectorToolsJob extends \Domain\Integration\Tool\Jobs\EmbedConnectorToolsJob {}
