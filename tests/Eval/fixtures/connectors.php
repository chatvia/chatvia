<?php

declare(strict_types=1);

/**
 * The connector catalog the eval seeds before scoring ({@see tests/Eval/README.md}).
 *
 * Shape — `slug => [name, description, tools[]]`, each tool `[name, scope, description]`:
 *
 *     'stripe' => [
 *         'name'        => 'Stripe',
 *         'description' => 'Payments, charges, refunds and customer billing.',
 *         'tools' => [
 *             ['name' => 'create_refund', 'scope' => 'write', 'description' => '…'],
 *             ['name' => 'get_charge',    'scope' => 'read',  'description' => '…'],
 *         ],
 *     ],
 *
 * `scope` is `read` | `write` and MUST match how the tool behaves — it's what the
 * wrong-mutation metric keys on. `name` + `description` are embedded together with
 * the connector's `name`/`description` (mirroring EmbedMcpToolsAction), so they
 * should read like the real tool's `tools/list` entry.
 *
 * To make the eval reflect YOUR deployment, replace this synthetic set with your
 * real connectors — see the "Mirror your real catalog" section in README.md for a
 * one-shot DB dump you can paste in here.
 *
 * Every `connector/tool` referenced from cases.php must exist here, with a matching
 * scope — the eval validates this up front and fails loudly on a mismatch.
 */
return [
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
];
