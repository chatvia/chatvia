<?php

use Illuminate\Pagination\Cursor;
use Support\Pagination\CursorPage;

/**
 * The "next page" cursor comes back from the client, so CursorPage reads it
 * only when it carries exactly the list's columns, each with a value of its
 * kind; anything else means the first page, never a query that fails. The
 * API lists that page with it are in ApiListPaginationTest.
 */
it('reads back a cursor the list handed out, a number as text for a text column and a real timestamp', function (): void {
    $cursor = CursorPage::decode(new Cursor(['agents.id' => 41])->encode(), ['agents.id' => 'int']);
    $text = CursorPage::decode(new Cursor(['topics.slug' => 7])->encode(), ['topics.slug' => 'string']);
    $timestamp = CursorPage::decode(new Cursor(['logs.created_at' => '2026-10-24 09:30:00', 'logs.id' => 9])->encode(), ['logs.created_at' => 'timestamp', 'logs.id' => 'int']);

    expect($cursor)->toBeInstanceOf(Cursor::class)
        ->and($cursor->parameter('agents.id'))->toBe(41)
        ->and($text?->parameter('topics.slug'))->toBe('7')
        ->and($timestamp)->toBeInstanceOf(Cursor::class);
});

it('reads a cursor the list did not hand out as no cursor', function (?string $encoded, array $columns): void {
    expect(CursorPage::decode($encoded, $columns))->toBeNull();
})->with([
    'none' => [null, ['agents.id' => 'int']],
    'not a cursor' => ['not-a-cursor', ['agents.id' => 'int']],
    'another column' => fn (): array => [new Cursor(['other' => 1])->encode(), ['agents.id' => 'int']],
    'a value of the wrong kind' => fn (): array => [new Cursor(['agents.id' => 'abc'])->encode(), ['agents.id' => 'int']],
    'one that points back' => fn (): array => [new Cursor(['agents.id' => 41], pointsToNextItems: false)->encode(), ['agents.id' => 'int']],
    'a timestamp that is not a real moment' => fn (): array => [new Cursor(['logs.created_at' => '2026-02-30 09:30:00', 'logs.id' => 9])->encode(), ['logs.created_at' => 'timestamp', 'logs.id' => 'int']],
]);
