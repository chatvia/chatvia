<?php

namespace Domain\Conversation\Data\Approval;

use Spatie\LaravelData\Data;

class ResolveToolApprovalData extends Data
{
    /**
     * @param  array<string, string>  $decisions  tool_call_id => 'approve' | 'deny'
     */
    public function __construct(
        public array $decisions,
    ) {}
}
