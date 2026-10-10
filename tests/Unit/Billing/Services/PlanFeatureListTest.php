<?php

use Domain\Billing\Contracts\Entitlements;
use Domain\Billing\Data\Resources\Package\BillingPackageCapabilitiesData;
use Domain\Billing\Data\Resources\Package\PlanFeatureData;
use Domain\Billing\Enums\BillingInterval;
use Domain\Billing\Enums\PlanFeature;
use Domain\Billing\Models\BillingPlan;
use Domain\Billing\Services\Catalog\BillingCatalog;
use Domain\Billing\Services\Catalog\PlanFeatureList;

/**
 * The plan card bullets are built from the plan (limits, feature switches,
 * extra lines) and rendered by the dashboard with the vue-i18n templates
 * `billing.features.*`. These tests pin the bullets of the four built-in
 * plans, the edge cases of custom plans, and that every template exists in
 * every dashboard language.
 */
/**
 * The bullets of a plan as short strings: `key`, `key: count` or `text: line`.
 *
 * @return list<string>
 */
function planFeatureLines(BillingPlan $plan, BillingInterval $interval = BillingInterval::Monthly): array
{
    return app(PlanFeatureList::class)->forInterval($plan, $interval)
        ->map(fn (PlanFeatureData $feature): string => match (true) {
            ! $feature->key instanceof PlanFeature => 'text: '.$feature->text,
            isset($feature->params['count']) => $feature->key->value.': '.$feature->params['count'],
            default => $feature->key->value,
        })
        ->all();
}

/**
 * The bullets of a plan in English, rendered the way the dashboard does:
 * the `en.json` template, its singular form for a count of 1, the count with
 * thousands separators, and the default brand name.
 *
 * @return list<string>
 */
function planFeatureEnglish(BillingPlan $plan, BillingInterval $interval = BillingInterval::Monthly): array
{
    $templates = json_decode((string) file_get_contents(resource_path('js/i18n/locales/en.json')), true)['billing']['features'];

    return app(PlanFeatureList::class)->forInterval($plan, $interval)
        ->map(function (PlanFeatureData $feature) use ($templates): string {
            if (! $feature->key instanceof PlanFeature) {
                return (string) $feature->text;
            }

            $forms = array_map(trim(...), explode('|', $templates[$feature->key->value]));
            $count = $feature->params['count'] ?? null;
            $template = $count === 1 ? $forms[0] : array_last($forms);

            return strtr($template, ['{count}' => number_format((int) $count), '{brand}' => 'Chatvia']);
        })
        ->all();
}

function builtInPlan(string $key): BillingPlan
{
    return app(BillingCatalog::class)->plan($key) ?? throw new RuntimeException("No {$key} plan.");
}

it('lists the Starter bullets of the pricing page in order', function (): void {
    expect(planFeatureEnglish(builtInPlan('starter')))->toBe([
        '3,000 messages per month',
        '2 AI agents',
        '2,000 pages (PDF, Word, Text)',
        '2,000 website links',
        '500 minutes audio/video',
        '1 team',
        '5 members (team members + chatters)',
        'MCP',
        'Function Calling',
        '90 days of chat history',
    ])->and(planFeatureLines(builtInPlan('starter'), BillingInterval::Yearly)[0])->toBe('messages_per_year: 39000')
        ->and(planFeatureEnglish(builtInPlan('starter'), BillingInterval::Yearly)[0])->toBe('39,000 messages per year');
});

it('lists the Standard bullets, with every feature switch that is on', function (): void {
    expect(planFeatureEnglish(builtInPlan('standard')))->toBe([
        '15,000 messages per month',
        '10 AI agents',
        '5,000 pages (PDF, Word, Text)',
        '5,000 website links',
        '2,000 minutes audio/video',
        'Sentiment analysis',
        'Handoff',
        'API access',
        'Evals',
        'Guardrails',
        '5 teams',
        '20 members (team members + chatters)',
        'MCP',
        'Function Calling',
        '180 days of chat history',
    ])->and(planFeatureEnglish(builtInPlan('standard'), BillingInterval::Yearly)[0])->toBe('192,000 messages per year');
});

it('lists the Pro bullets with its highlight last', function (): void {
    expect(planFeatureEnglish(builtInPlan('pro')))->toBe([
        '50,000 messages per month',
        '20 AI agents',
        '10,000 pages (PDF, Word, Text)',
        '10,000 website links',
        '10,000 minutes audio/video',
        '2 custom domains',
        'Sentiment analysis',
        'Tone analysis',
        'Topic analysis',
        'Conversation Pulse',
        'Handoff',
        'API access',
        'Evals',
        'Guardrails',
        'Audit log',
        'White-label (remove Chatvia branding)',
        'Network access control (IP & geo)',
        'SIEM webhook (real-time security events)',
        '10 teams',
        '100 members (team members + chatters)',
        'MCP',
        'Function Calling',
        '366 days of chat history',
        'Custom integrations',
    ])->and(planFeatureEnglish(builtInPlan('pro'), BillingInterval::Yearly)[0])->toBe('648,000 messages per year');
});

it('words the limits of a contact-sales plan as custom or unlimited', function (): void {
    $enterprise = builtInPlan('enterprise');

    expect(planFeatureEnglish($enterprise))->toBe([
        'Custom message volume',
        'Unlimited AI agents',
        'Custom knowledge base',
        'Custom website links',
        'Custom audio/video',
        'Custom domains',
        'Sentiment analysis',
        'Tone analysis',
        'Topic analysis',
        'Conversation Pulse',
        'Handoff',
        'API access',
        'Evals',
        'Guardrails',
        'Audit log',
        'White-label (remove Chatvia branding)',
        'Network access control (IP & geo)',
        'SIEM webhook (real-time security events)',
        'Unlimited teams',
        'Unlimited members',
        'MCP',
        'Function Calling',
        'Custom data retention',
        'Custom integrations',
        'EU data residency',
        'SSO / SAML',
        'Uptime SLA',
        'Dedicated support',
        'Custom contract & DPA',
    ])->and(planFeatureLines($enterprise, BillingInterval::Yearly))->toBe(planFeatureLines($enterprise));
});

it('leaves out zero limits, words huge limits as unlimited and keeps odd counts', function (): void {
    $plan = BillingPlan::factory()->create([
        'capabilities' => new BillingPackageCapabilitiesData(tone: true, siemWebhook: true, retentionDays: 1),
        'quota_limits' => [
            'monthly' => ['messages_limit' => 1],
            'yearly' => ['messages_limit' => Entitlements::UNLIMITED],
            'fixed' => [
                'agents_limit' => Entitlements::UNLIMITED,
                'pages_limit' => 0,
                'links_limit' => 1_500_000_000,
                'workspace_users_limit' => 1_234_567,
                'teams_limit' => 1,
            ],
        ],
        'extra_features' => ['  ', 'Priority onboarding', ''],
    ]);

    expect(planFeatureLines($plan))->toBe([
        'messages_per_month: 1',
        'agents_unlimited',
        'links_unlimited',
        'tone',
        'siem_webhook',
        'teams: 1',
        'members: 1234567',
        'mcp',
        'function_calling',
        'retention: 1',
        'text: Priority onboarding',
    ])->and(planFeatureEnglish($plan))->toContain('1 message per month', '1 team', '1,234,567 members (team members + chatters)', '1 day of chat history')
        ->and(planFeatureLines($plan, BillingInterval::Yearly)[0])->toBe('messages_unlimited');
});

it('shows a custom contact-sales plan with the contact-sales wording and its extra lines last', function (): void {
    $plan = BillingPlan::factory()->contactSales()->create([
        'capabilities' => new BillingPackageCapabilitiesData(handoff: true),
        'extra_features' => ['On-premise deployment'],
    ]);

    expect(planFeatureLines($plan))->toBe([
        'messages_custom',
        'agents_unlimited',
        'pages_custom',
        'links_custom',
        'minutes_custom',
        'custom_domains_custom',
        'handoff',
        'teams_unlimited',
        'members_unlimited',
        'mcp',
        'function_calling',
        'retention_custom',
        'text: On-premise deployment',
    ]);
});

it('adds the ticked highlights in their fixed order, translated, before the free-text lines', function (): void {
    $plan = BillingPlan::factory()->create([
        'quota_limits' => ['fixed' => ['custom_domains_limit' => 1]],
        'highlights' => ['sso_saml', 'not_a_highlight', 'mcp', 'custom_integrations'],
        'extra_features' => ['Onboarding call'],
    ]);

    expect(array_slice(planFeatureLines($plan), -3))->toBe(['custom_integrations', 'sso_saml', 'text: Onboarding call'])
        ->and(planFeatureLines($plan))->toContain('custom_domains: 1')
        ->and(planFeatureEnglish($plan))->toContain('1 custom domain', 'Custom integrations', 'SSO / SAML');
});

it('has a template for every bullet in every dashboard language', function (string $locale): void {
    $messages = json_decode((string) file_get_contents(resource_path("js/i18n/locales/{$locale}.json")), true);
    $templates = $messages['billing']['features'] ?? [];

    foreach (PlanFeature::cases() as $feature) {
        $template = $templates[$feature->value] ?? null;

        expect($template)->toBeString("{$locale}: billing.features.{$feature->value} is missing");

        // A counted bullet needs exactly the two plural forms the dashboard
        // uses (singular | plural); the others must not look plural.
        $forms = str_contains((string) $template, '{count}') ? 2 : 1;
        expect(explode('|', (string) $template))->toHaveCount($forms, "{$locale}: billing.features.{$feature->value}");
    }

    expect($messages['billing']['catalog']['packages']['starter'] ?? [])->not->toHaveKey('features');
})->with(['ar', 'da', 'de', 'en', 'es', 'fa', 'fi', 'fr', 'hi', 'id', 'ja', 'ko', 'no', 'pl', 'pt', 'ru', 'sv', 'tr', 'vi', 'zh']);
