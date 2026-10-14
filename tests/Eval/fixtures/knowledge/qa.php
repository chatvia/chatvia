<?php

declare(strict_types=1);

/**
 * Saved Q&A answers for the knowledge retrieval eval, and messages that
 * should or should not get them by meaning (see ../../README.md). `match` is
 * the index of the entry the message should get, or null for none.
 */
return [
    'entries' => [
        ['title' => 'Opening hours', 'questions' => ['What are your opening hours?', 'When are you open?'], 'answer' => 'Our shops are open Monday to Saturday.'],
        ['title' => 'Gift cards', 'questions' => ['Do you sell gift cards?'], 'answer' => 'Yes, in every shop and online.'],
        ['title' => 'مواعيد العمل', 'questions' => ['ما هي مواعيد العمل؟'], 'answer' => 'من السبت إلى الخميس.'],
    ],
    'cases' => [
        ['query' => 'At what time do you open?', 'match' => 0],
        ['query' => 'what time do the shops open', 'match' => 0],
        ['query' => 'Can I buy a gift card?', 'match' => 1],
        ['query' => 'متى تفتحون؟', 'match' => 2],
        ['query' => 'hours', 'match' => null],
        ['query' => 'How long does delivery take?', 'match' => null],
        ['query' => 'Can I return a gift?', 'match' => null],
        ['query' => 'Are you open on public holidays?', 'match' => null],
    ],
];
