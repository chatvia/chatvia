<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Admin\Issue\Enums\IssueActorKind;
use App\Admin\Issue\Enums\IssueOriginKind;
use App\Admin\Issue\Models\Issue;
use App\Admin\Issue\Models\IssueOccurrence;
use Domain\Conversation\Jobs\GenerateMediaJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds an occurrence of a new issue, in a background job with nobody
 * behind it, just now.
 *
 * @extends Factory<IssueOccurrence>
 */
class IssueOccurrenceFactory extends Factory
{
    #[\Override]
    protected $model = IssueOccurrence::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'issue_id' => Issue::factory(),
            'actor_kind' => IssueActorKind::System,
            'origin_kind' => IssueOriginKind::Job,
            'origin_name' => GenerateMediaJob::class,
            'model' => 'fal-ai/wan/v2.2-a14b/text-to-video',
            'http_status' => 403,
            'endpoint' => 'queue.fal.run/fal-ai/wan/v2.2-a14b/text-to-video',
            'message' => 'Invalid API key',
            'occurred_at' => now(),
        ];
    }
}
