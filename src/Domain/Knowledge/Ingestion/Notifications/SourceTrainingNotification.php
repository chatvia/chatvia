<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Notifications;

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Notification\Data\NotificationAgentData;
use Domain\Notification\Data\NotificationContentData;
use Domain\Notification\Data\NotificationMessageData;
use Domain\Notification\Enums\NotificationKind;
use Domain\Notification\Notifications\InboxNotification;
use Illuminate\Support\Str;
use Support\MimeType;

/**
 * A source finished training, or failed to. It goes to the people who may
 * train that kind of source on the agent ({@see permission()}) and opens the
 * agent's page for that kind. Finished ones gather per agent while unread
 * ("5 sources finished training"); each failure stands alone.
 */
class SourceTrainingNotification extends InboxNotification
{
    private function __construct(
        private readonly SourceFile|SourceText|SourceWebsite $source,
        private readonly Agent $agent,
        private readonly NotificationKind $kind,
    ) {}

    public static function trained(SourceFile|SourceText|SourceWebsite $source, Agent $agent): self
    {
        return new self($source, $agent, NotificationKind::SourceTrained);
    }

    public static function failed(SourceFile|SourceText|SourceWebsite $source, Agent $agent): self
    {
        return new self($source, $agent, NotificationKind::SourceTrainingFailed);
    }

    /** The team permission that trains this kind of source. */
    public static function permission(SourceFile|SourceText|SourceWebsite $source): string
    {
        return 'agent.sources.train.'.match (self::sourceKind($source)) {
            'audio' => 'audio',
            'video' => 'video',
            'text' => 'text',
            'website' => 'web',
            default => 'files',
        };
    }

    public function kind(): NotificationKind
    {
        return $this->kind;
    }

    public function toInbox(User $notifiable): NotificationMessageData
    {
        $sourceKind = self::sourceKind($this->source);

        $route = match ($sourceKind) {
            'audio' => 'agents.sources.audio',
            'video' => 'agents.sources.video',
            'text' => 'agents.sources.text.index',
            'website' => 'agents.sources.web',
            default => 'agents.sources.files',
        };

        return $this->message(
            new NotificationContentData(
                url: route($route, ['agent' => $this->agent], absolute: false),
                agent: NotificationAgentData::fromAgent($this->agent),
                subject: $this->subject(),
                detail: $sourceKind,
            ),
            $this->kind === NotificationKind::SourceTrained ? "training:{$this->agent->external_id}" : null,
        );
    }

    /** `file`, `audio`, `video`, `text` or `website`. */
    private static function sourceKind(SourceFile|SourceText|SourceWebsite $source): string
    {
        return match (true) {
            $source instanceof SourceWebsite => 'website',
            $source instanceof SourceText => 'text',
            MimeType::isAudio($source->mime_type) => 'audio',
            MimeType::isVideo($source->mime_type) => 'video',
            default => 'file',
        };
    }

    private function subject(): string
    {
        return match (true) {
            $this->source instanceof SourceFile => $this->source->filename,
            $this->source instanceof SourceText => Str::limit(Str::squish($this->source->excerpt), 60),
            $this->source instanceof SourceWebsite => Str::limit(Str::replaceMatches('#^https?://(www\.)?#i', '', $this->source->url) ?? $this->source->url, 60),
        };
    }
}
