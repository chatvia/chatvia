<?php

declare(strict_types=1);

/**
 * Labeled dataset for the tool-routing eval ({@see tests/Eval/ToolRoutingEvalTest.php}).
 *
 * `catalog` is a synthetic-but-realistic connector catalog: each connector has a
 * mix of read and write tools, with deliberate cross-connector collisions on the
 * write set — "create_ticket" (Zendesk) vs "create_issue" (Linear) vs
 * "create_issue" (GitHub) — the exact confusable-mutation case the retriever has
 * to get right. `scope` mirrors the real `connector_tools.scope` enum
 * (read/write); it's what lets the harness score the *wrong-mutation* rate (a
 * write tool from the wrong connector surfacing for a request).
 *
 * `cases` are labeled queries: the tool a human would pick by hand. The harness
 * scores the live router against these — top-1, recall@5, MRR, and the
 * wrong-mutation rate — not "did it surface something usable".
 *
 * Add cases freely. The point is to grow this toward the real distribution of
 * what users ask, so threshold changes (#1 per-scope gating) move a real number.
 */
return [
    'catalog' => [
        'stripe' => [
            'name' => 'Stripe',
            'description' => 'Payments, charges, refunds and customer billing.',
            'tools' => [
                ['name' => 'create_refund', 'scope' => 'write', 'description' => 'Refund a charge back to a customer.'],
                ['name' => 'create_payment_link', 'scope' => 'write', 'description' => 'Create a shareable link to collect a payment.'],
                ['name' => 'get_charge', 'scope' => 'read', 'description' => 'Retrieve the details of a single payment charge.'],
                ['name' => 'list_invoices', 'scope' => 'read', 'description' => 'List a customer\'s invoices and their amounts.'],
            ],
        ],
        'zendesk' => [
            'name' => 'Zendesk',
            'description' => 'Customer support tickets and agent replies.',
            'tools' => [
                ['name' => 'create_ticket', 'scope' => 'write', 'description' => 'Open a new customer support ticket.'],
                ['name' => 'reply_to_ticket', 'scope' => 'write', 'description' => 'Post a reply to the customer on an existing ticket.'],
                ['name' => 'search_tickets', 'scope' => 'read', 'description' => 'Search existing support tickets by keyword or status.'],
            ],
        ],
        'linear' => [
            'name' => 'Linear',
            'description' => 'Engineering issue tracking and project planning.',
            'tools' => [
                ['name' => 'create_issue', 'scope' => 'write', 'description' => 'File a new engineering issue in a Linear team.'],
                ['name' => 'update_issue_status', 'scope' => 'write', 'description' => 'Move an issue to a different workflow state.'],
                ['name' => 'search_issues', 'scope' => 'read', 'description' => 'Search Linear issues assigned to or mentioning a user.'],
            ],
        ],
        'github' => [
            'name' => 'GitHub',
            'description' => 'Source code repositories, issues and pull requests.',
            'tools' => [
                ['name' => 'create_issue', 'scope' => 'write', 'description' => 'Open a new issue on a GitHub repository.'],
                ['name' => 'create_pull_request', 'scope' => 'write', 'description' => 'Open a pull request to merge a branch.'],
                ['name' => 'search_repositories', 'scope' => 'read', 'description' => 'Search GitHub repositories by name or topic.'],
            ],
        ],
        'notion' => [
            'name' => 'Notion',
            'description' => 'Team wiki, documents and knowledge pages.',
            'tools' => [
                ['name' => 'create_page', 'scope' => 'write', 'description' => 'Create a new document page in a Notion workspace.'],
                ['name' => 'search_pages', 'scope' => 'read', 'description' => 'Search Notion pages by title or content.'],
            ],
        ],
        'slack' => [
            'name' => 'Slack',
            'description' => 'Team chat channels and direct messages.',
            'tools' => [
                ['name' => 'send_message', 'scope' => 'write', 'description' => 'Post a message to a Slack channel or user.'],
                ['name' => 'search_messages', 'scope' => 'read', 'description' => 'Search the Slack message history by keyword.'],
            ],
        ],
        'hubspot' => [
            'name' => 'HubSpot',
            'description' => 'CRM contacts, companies and sales deals.',
            'tools' => [
                ['name' => 'create_contact', 'scope' => 'write', 'description' => 'Add a new contact person to the CRM.'],
                ['name' => 'search_contacts', 'scope' => 'read', 'description' => 'Look up contacts in the CRM by name or email.'],
            ],
        ],
        'google_calendar' => [
            'name' => 'Google Calendar',
            'description' => 'Calendar events, meetings and availability.',
            'tools' => [
                ['name' => 'create_event', 'scope' => 'write', 'description' => 'Schedule a new calendar event or meeting.'],
                ['name' => 'list_events', 'scope' => 'read', 'description' => 'List upcoming events on a calendar.'],
            ],
        ],
    ],

    // query → the tool a human would pick. `write` is the labelled side-effect
    // class of the expected tool (used to scope the wrong-mutation metric).
    'cases' => [
        ['query' => 'refund this customer 20 dollars', 'connector' => 'stripe', 'tool' => 'create_refund', 'write' => true],
        ['query' => 'send the customer a link to pay their invoice', 'connector' => 'stripe', 'tool' => 'create_payment_link', 'write' => true],
        ['query' => 'how much was charged on payment ch_1A2B', 'connector' => 'stripe', 'tool' => 'get_charge', 'write' => false],
        ['query' => 'open a support ticket for the bug this user reported', 'connector' => 'zendesk', 'tool' => 'create_ticket', 'write' => true],
        ['query' => 'can someone get back to this angry customer', 'connector' => 'zendesk', 'tool' => 'reply_to_ticket', 'write' => true],
        ['query' => 'show me the open support tickets about billing', 'connector' => 'zendesk', 'tool' => 'search_tickets', 'write' => false],
        ['query' => 'file an engineering issue to track this crash', 'connector' => 'linear', 'tool' => 'create_issue', 'write' => true],
        ['query' => 'what issues are assigned to me this sprint', 'connector' => 'linear', 'tool' => 'search_issues', 'write' => false],
        ['query' => 'open a pull request for the parser fix', 'connector' => 'github', 'tool' => 'create_pull_request', 'write' => true],
        ['query' => 'find the repo that has our markdown parser', 'connector' => 'github', 'tool' => 'search_repositories', 'write' => false],
        ['query' => 'write up a page documenting the outage', 'connector' => 'notion', 'tool' => 'create_page', 'write' => true],
        ['query' => 'post an update to the team channel about the deploy', 'connector' => 'slack', 'tool' => 'send_message', 'write' => true],
        ['query' => 'add John from Acme as a new contact', 'connector' => 'hubspot', 'tool' => 'create_contact', 'write' => true],
        ['query' => 'what meetings do I have tomorrow', 'connector' => 'google_calendar', 'tool' => 'list_events', 'write' => false],
    ],
];
