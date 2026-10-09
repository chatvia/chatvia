<?php

declare(strict_types=1);

namespace Domain\Conversation\Jobs;

/**
 * The old class name of {@see \Domain\Integration\Tool\Jobs\EmbedConnectorToolsJob}. A
 * queued job is stored with its class name, so this empty subclass lets jobs
 * that were queued under the old name still run. Nothing dispatches it; it can
 * be deleted once no job with this name is left in the queue.
 *
 * @deprecated Dispatch {@see \Domain\Integration\Tool\Jobs\EmbedConnectorToolsJob} instead.
 */
final class EmbedConnectorToolsJob extends \Domain\Integration\Tool\Jobs\EmbedConnectorToolsJob {}
