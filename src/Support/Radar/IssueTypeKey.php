<?php

declare(strict_types=1);

namespace Support\Radar;

/**
 * The keys of the kinds of issue code can raise ({@see IssueRaised}), so a
 * call site never types a bare string. Each key has a type class listed in
 * config/radar.php. Stored, so a key must not change.
 */
final class IssueTypeKey
{
    public const string KEY_REFUSED = 'key_refused';

    public const string KEY_MISSING = 'key_missing';

    public const string TURNED_OFF = 'turned_off';

    public const string OUT_OF_CREDIT = 'out_of_credit';

    public const string MODEL_GONE = 'model_gone';

    public const string RATE_LIMITED = 'rate_limited';

    public const string SERVICE_DOWN = 'service_down';

    public const string UNREACHABLE = 'unreachable';

    public const string SEND_FAILED = 'send_failed';

    public const string BACKUP_FAILED = 'backup_failed';

    public const string LICENSE_INACTIVE = 'license_inactive';

    public const string JOB_FAILED = 'job_failed';

    public const string QUEUE_BACKLOG = 'queue_backlog';

    public const string TASK_FAILED = 'task_failed';

    public const string SERVICE_STOPPED = 'service_stopped';

    public const string MESSAGE_LIMIT_REACHED = 'message_limit_reached';

    public const string GENERATION_LIMIT_REACHED = 'generation_limit_reached';

    public const string FREE_LIMIT_REACHED = 'free_limit_reached';
}
