<?php

use Illuminate\Pagination\Cursor;
use Support\Pagination\CursorPage;

/**
 * The "next page" cursor comes back from the client, so CursorPage reads it
 * only when it carries exactly the list's columns, each with a value of its
 * kind; anything else means the first page, never a query that fails. The
 * API lists that page with it are in ApiListPaginationTest.
 */
it('reads back a cursor the list handed out', function (): void {
    $cursor = CursorPage::decode(new Cursor(['agents.id' => 41])->encode(), ['agents.id' => 'int']);

    expect($cursor)->toBeInstanceOf(Cursor::class)
        ->and($cursor->parameter('agents.id'))->toBe(41);
});

it('reads a cursor the list did not hand out as no cursor', function (?string $encoded): void {
    expect(CursorPage::decode($encoded, ['agents.id' => 'int']))->toBeNull();
})->with([
    'none' => [null],
    'not a cursor' => ['not-a-cursor'],
    'another column' => fn (): string => new Cursor(['other' => 1])->encode(),
    'a value of the wrong kind' => fn (): string => new Cursor(['agents.id' => 'abc'])->encode(),
    'a column too many' => fn (): string => new Cursor(['agents.id' => 1, 'other' => 1])->encode(),
    'one that points back' => fn (): string => new Cursor(['agents.id' => 41], pointsToNextItems: false)->encode(),
]);

it('hands a number on as text for a text column', function (): void {
    $cursor = CursorPage::decode(new Cursor(['topics.slug' => 7])->encode(), ['topics.slug' => 'string']);

    expect($cursor?->parameter('topics.slug'))->toBe('7');
});

it('takes a timestamp only as a real moment in the Y-m-d H:i:s form', function (string $timestamp, bool $read): void {
    $cursor = CursorPage::decode(new Cursor(['logs.created_at' => $timestamp, 'logs.id' => 9])->encode(), ['logs.created_at' => 'timestamp', 'logs.id' => 'int']);

    expect($cursor instanceof Cursor)->toBe($read);
})->with([
    'a real moment' => ['2026-10-24 09:30:00', true],
    'a day that does not exist' => ['2026-02-30 09:30:00', false],
    'another form' => ['2026-10-24T09:30:00Z', false],
]);
