<?php

declare(strict_types=1);

namespace App\Admin\Resources\Issues\Pages;

use App\Admin\Issue\Enums\IssueStatus;
use App\Admin\Issue\Models\Issue;
use App\Admin\Issue\Models\IssueOccurrence;
use App\Admin\Resources\Issues\IssueResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Js;

/**
 * One issue: what to do and where, a summary, the last 14 days, and every
 * time it happened in a tab below. From the header an admin resolves,
 * reopens, ignores or deletes it, or copies its details as plain text.
 */
class ViewIssue extends ViewRecord
{
    #[\Override]
    protected static string $resource = IssueResource::class;

    public function getTitle(): string
    {
        return $this->issue()->title();
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return false;
    }

    protected function getHeaderActions(): array
    {
        return [
            IssueResource::resolveAction()->record($this->issue()),
            Action::make('reopen')
                ->label('Reopen')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->visible(fn (): bool => $this->issue()->status !== IssueStatus::Open)
                ->action(fn () => $this->issue()->reopen()),
            Action::make('ignore')
                ->label('Ignore')
                ->icon(Heroicon::OutlinedEyeSlash)
                ->color('gray')
                ->tooltip('Ignored issues stay out of the sidebar count, even when they happen again.')
                ->visible(fn (): bool => $this->issue()->status !== IssueStatus::Ignored)
                ->action(fn () => $this->issue()->ignore()),
            Action::make('copy')
                ->label('Copy details')
                ->icon(Heroicon::OutlinedClipboardDocument)
                ->color('gray')
                ->alpineClickHandler(fn (): string => 'window.navigator.clipboard.writeText('.Js::from($this->plainText()).'); new FilamentNotification().title('.Js::from('Copied').').success().send()'),
            DeleteAction::make(),
        ];
    }

    private function issue(): Issue
    {
        $record = $this->getRecord();
        assert($record instanceof Issue);

        return $record;
    }

    /** The issue and its latest occurrence as text, to send on. */
    private function plainText(): string
    {
        $issue = $this->issue();
        $latest = $issue->latestOccurrence;

        return collect([
            $issue->title(),
            filled($issue->message) ? "The service said: {$issue->message}" : null,
            "Seen {$issue->occurrences_count} times, first {$issue->first_seen_at->toDateTimeString()}, last {$issue->last_seen_at->toDateTimeString()}",
            $latest instanceof IssueOccurrence ? "Last time: {$latest->originLabel()}" : null,
            $latest?->http_status !== null ? "HTTP status: {$latest->http_status}" : null,
            filled($latest?->endpoint) ? "Address: {$latest->endpoint}" : null,
            filled($issue->advice()) ? "What to do: {$issue->advice()}" : null,
        ])->filter()->join("\n");
    }
}
