<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Jobs;

use Domain\Knowledge\Retrieval\Jobs\UpdateRetrievalStatsJob as RetrievalStatsJob;

/**
 * The old class name of the retrieval-stats job. A queued job is stored with
 * its class name, so this alias lets jobs queued under the old name still run,
 * exactly like the new job. Nothing dispatches it; it can be deleted once no
 * job with this name is left in the queue.
 *
 * @deprecated Use {@see RetrievalStatsJob}.
 */
class UpdateRetrievalStatsJob extends RetrievalStatsJob {}
