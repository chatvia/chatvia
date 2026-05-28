<?php

namespace Support\Enums;

/**
 * How a record was created: `external` for records created through the public
 * API, `internal` for everything else.
 */
enum CreatedVia: string
{
    case Internal = 'internal';
    case External = 'external';
}
