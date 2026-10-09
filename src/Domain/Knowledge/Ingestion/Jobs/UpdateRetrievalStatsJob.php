<?php

namespace Domain\Knowledge\Ingestion\Jobs;

use Domain\Knowledge\Retrieval\Jobs\UpdateRetrievalStatsJob as RetrievalStatsJob;

/**
 * The retrieval-stats job's previous class name. Jobs queued before the move
 * to `Knowledge\Retrieval` still name this class in their payload, so it stays
 * for one release and runs exactly like the new job. Nothing dispatches it any
 * more; delete it once those queues have drained.
 *
 * @deprecated Use {@see RetrievalStatsJob}.
 */
class UpdateRetrievalStatsJob extends RetrievalStatsJob {}
