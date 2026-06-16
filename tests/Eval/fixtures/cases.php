<?php

declare(strict_types=1);

/**
 * Labeled queries for the tool-routing eval — the file to GROW ({@see README.md}).
 *
 * Each row is one query and the single tool a human would pick for it by hand:
 *
 *     ['query' => '<what the user typed>', 'connector' => '<slug>', 'tool' => '<tool name>', 'write' => <bool>],
 *
 *   - `connector` + `tool` must exist in connectors.php (the eval validates this).
 *   - `write` is the labelled side-effect class of the EXPECTED tool — true for a
 *     mutation (refund, create, reply), false for a read (search, get, list). It
 *     must match that tool's `scope` in connectors.php.
 *
 * This is where real traffic goes: paste anonymized queries from your logs, label
 * the one tool you'd want fired, and re-run. The synthetic rows below are seeds —
 * the closer this file gets to your real query distribution, the more the
 * scoreboard means. Aim for a spread of reads and writes, and include the
 * confusable cases (ticket vs issue, refund vs invoice) where the router earns its
 * keep. ~50+ rows makes single-case noise (one row ≈ 100/N %) stop mattering.
 */
return [
    // --- Stripe ---
    ['query' => 'refund this customer 20 dollars', 'connector' => 'stripe', 'tool' => 'create_refund', 'write' => true],
    ['query' => 'send the customer a link to pay their invoice', 'connector' => 'stripe', 'tool' => 'create_payment_link', 'write' => true],
    ['query' => 'how much was charged on payment ch_1A2B', 'connector' => 'stripe', 'tool' => 'get_charge', 'write' => false],

    // --- Zendesk (collides with Linear/GitHub on "issue" vs "ticket") ---
    ['query' => 'open a support ticket for the bug this user reported', 'connector' => 'zendesk', 'tool' => 'create_ticket', 'write' => true],
    ['query' => 'can someone get back to this angry customer', 'connector' => 'zendesk', 'tool' => 'reply_to_ticket', 'write' => true],
    ['query' => 'show me the open support tickets about billing', 'connector' => 'zendesk', 'tool' => 'search_tickets', 'write' => false],

    // --- Linear / GitHub ---
    ['query' => 'file an engineering issue to track this crash', 'connector' => 'linear', 'tool' => 'create_issue', 'write' => true],
    ['query' => 'what issues are assigned to me this sprint', 'connector' => 'linear', 'tool' => 'search_issues', 'write' => false],
    ['query' => 'open a pull request for the parser fix', 'connector' => 'github', 'tool' => 'create_pull_request', 'write' => true],
    ['query' => 'find the repo that has our markdown parser', 'connector' => 'github', 'tool' => 'search_repositories', 'write' => false],

    // --- Notion / Slack / HubSpot / Calendar ---
    ['query' => 'write up a page documenting the outage', 'connector' => 'notion', 'tool' => 'create_page', 'write' => true],
    ['query' => 'post an update to the team channel about the deploy', 'connector' => 'slack', 'tool' => 'send_message', 'write' => true],
    ['query' => 'add John from Acme as a new contact', 'connector' => 'hubspot', 'tool' => 'create_contact', 'write' => true],
    ['query' => 'what meetings do I have tomorrow', 'connector' => 'google_calendar', 'tool' => 'list_events', 'write' => false],
];
