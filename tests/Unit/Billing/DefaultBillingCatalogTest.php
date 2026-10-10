<?php

use Domain\Billing\Models\BillingAddon;
use Domain\Billing\Models\BillingPlan;

/**
 * A new install starts with four example plans and the six add-ons (whose
 * kinds are fixed in code), created by their table migrations without Stripe
 * prices. An admin edits them under Billing → Plans / Add-ons.
 */
it('starts with four example plans with their prices, flags, capabilities and limits', function (): void {
    $plans = BillingPlan::query()->orderBy('sort_order')->get()->keyBy('key');

    expect($plans->keys()->all())->toBe(['starter', 'standard', 'pro', 'enterprise'])
        ->and($plans->where('is_default', true)->keys()->all())->toBe(['starter'])
        ->and($plans->every(fn (BillingPlan $plan): bool => $plan->is_active))->toBeTrue()
        ->and($plans->where('is_popular', true)->keys()->all())->toBe(['standard'])
        ->and($plans->where('contact_sales', true)->keys()->all())->toBe(['enterprise']);

    expect($plans->map(fn (BillingPlan $plan): array => [
        $plan->name, $plan->monthly_price_cents, $plan->yearly_price_cents, $plan->icon, $plan->currency,
    ])->all())->toBe([
        'starter' => ['Starter', 4900, 46800, 'zap', 'eur'],
        'standard' => ['Standard', 19900, 190800, 'star', 'eur'],
        'pro' => ['Pro', 49900, 478800, 'crown', 'eur'],
        'enterprise' => ['Enterprise', 0, 0, 'building', 'eur'],
    ]);

    expect($plans['starter']->description)->toBe('For small businesses getting started.')
        ->and($plans['enterprise']->description)->toBe('For organizations with security, compliance, and scale needs.');

    expect($plans['starter']->quota_limits)->toEqual([
        'monthly' => ['messages_limit' => 3000],
        'yearly' => ['messages_limit' => 39000],
        'fixed' => [
            'agents_limit' => 2, 'pages_limit' => 2000, 'minutes_limit' => 500, 'links_limit' => 2000,
            'custom_domains_limit' => 0, 'workspace_users_limit' => 5, 'teams_limit' => 1,
        ],
    ])->and($plans['standard']->quota_limits)->toEqual([
        'monthly' => ['messages_limit' => 15000],
        'yearly' => ['messages_limit' => 192000],
        'fixed' => [
            'agents_limit' => 10, 'pages_limit' => 5000, 'minutes_limit' => 2000, 'links_limit' => 5000,
            'custom_domains_limit' => 0, 'workspace_users_limit' => 20, 'teams_limit' => 5,
        ],
    ])->and($plans['pro']->quota_limits)->toEqual([
        'monthly' => ['messages_limit' => 50000],
        'yearly' => ['messages_limit' => 648000],
        'fixed' => [
            'agents_limit' => 20, 'pages_limit' => 10000, 'minutes_limit' => 10000, 'links_limit' => 10000,
            'custom_domains_limit' => 2, 'workspace_users_limit' => 100, 'teams_limit' => 10,
        ],
    ])->and($plans['enterprise']->quota_limits)->toEqual([
        'monthly' => ['messages_limit' => 200000],
        'yearly' => ['messages_limit' => 2400000],
        'fixed' => [
            'agents_limit' => 100, 'pages_limit' => 100000, 'minutes_limit' => 100000, 'links_limit' => 100000,
            'custom_domains_limit' => 10, 'workspace_users_limit' => 1000, 'teams_limit' => 100,
        ],
    ]);

    $allOn = [
        'sentiment' => true, 'tone' => true, 'topics' => true, 'handoff' => true, 'api' => true, 'pulse' => true,
        'evals' => true, 'guardrails' => true, 'audit' => true, 'removeBranding' => true, 'security' => true,
        'siemWebhook' => true, 'retentionDays' => 366,
    ];

    expect($plans['starter']->capabilities->toArray())->toBe([
        ...array_map(fn (): bool => false, $allOn), 'retentionDays' => 90,
    ])->and($plans['standard']->capabilities->toArray())->toBe([
        ...array_map(fn (): bool => false, $allOn),
        'sentiment' => true, 'handoff' => true, 'api' => true, 'evals' => true, 'guardrails' => true,
        'retentionDays' => 180,
    ])->and($plans['pro']->capabilities->toArray())->toBe($allOn)
        ->and($plans['enterprise']->capabilities->toArray())->toBe($allOn);

    expect($plans['pro']->highlights)->toBe(['custom_integrations'])
        ->and($plans['enterprise']->highlights)->toBe([
            'custom_integrations', 'eu_data_residency', 'sso_saml', 'uptime_sla', 'dedicated_support', 'custom_contract',
        ])
        ->and($plans['starter']->extra_features)->toBe([])
        ->and($plans['starter']->legacy_stripe_price_ids)->toBe([]);
});

it('starts with the six add-ons with their prices and units', function (): void {
    $addons = BillingAddon::query()->get()->keyBy('key');

    expect($addons->map(fn (BillingAddon $addon): array => [
        $addon->name, $addon->price_cents, $addon->billing_unit, $addon->icon, $addon->unit_messages, $addon->is_active,
    ])->sortKeys()->all())->toBe([
        'auto_recharge_credits' => ['Auto Recharge Credits', 1000, 'per 1,000 credits', 'credit-card', 1000, true],
        'custom_domains' => ['Custom Domain', 2900, 'per domain/month', 'globe', null, true],
        'extra_agents' => ['Extra AI Agents', 500, 'per agent/month', 'bot', null, true],
        'extra_message_credits' => ['Extra Message Credits', 900, 'per 1,000 credits/month', 'message-circle', 1000, true],
        'members' => ['Members', 900, 'per member/month', 'users', null, true],
        'remove_branding' => ['Remove Chatvia Branding', 1900, 'per month', 'sparkles', null, true],
    ]);

    expect($addons['members']->description)->toBe('Add more team members to your workspace.');
});
