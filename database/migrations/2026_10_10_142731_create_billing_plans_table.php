<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The plans a workspace can subscribe to, managed in the admin panel.
 *
 * `key` is the plan id used everywhere (Stripe metadata, `user_billing.plan`,
 * the frontend), so it never changes after the plan is created. Archived plans
 * (`is_active` false) stay in the table: existing subscribers still resolve to
 * them. `legacy_stripe_price_ids` maps every older Stripe price of the plan to
 * its interval (`monthly` / `yearly`), so renewals of subscriptions on an old
 * price still find the plan. At most one plan is the default.
 *
 * It starts with four example plans (Starter is the default, Enterprise is
 * sold through sales). They have no Stripe prices: an admin edits them under
 * Billing → Plans, and saving creates the prices in Stripe.
 */
return new class extends Migration
{
    /** Every plan feature flag; a flag a plan does not list is off. */
    private const array CAPABILITY_FLAGS = [
        'sentiment', 'tone', 'topics', 'handoff', 'api', 'pulse', 'evals',
        'guardrails', 'audit', 'removeBranding', 'security', 'siemWebhook',
    ];

    public function up(): void
    {
        Schema::create('billing_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('icon', 32)->nullable();
            $table->integer('sort_order')->default(0);

            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_popular')->default(false);
            $table->boolean('contact_sales')->default(false);

            // Display amounts in the smallest currency unit. Stripe charges the
            // amount of the price behind the Stripe price id.
            $table->unsignedInteger('monthly_price_cents')->default(0);
            $table->unsignedInteger('yearly_price_cents')->nullable();
            $table->string('currency', 3);

            $table->string('stripe_product_id')->nullable();
            $table->string('stripe_monthly_price_id')->nullable();
            $table->string('stripe_yearly_price_id')->nullable();
            $table->jsonb('legacy_stripe_price_ids')->default('{}');

            $table->jsonb('capabilities')->default('{}');
            $table->jsonb('quota_limits')->default('{}');
            $table->jsonb('highlights')->default('[]');
            $table->jsonb('extra_features')->default('[]');

            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX billing_plans_one_default ON billing_plans (is_default) WHERE is_default');

        $now = now();

        foreach ($this->plans() as $key => $plan) {
            DB::table('billing_plans')->insert([
                ...$plan,
                'key' => $key,
                'is_default' => $key === 'starter',
                'is_active' => true,
                'currency' => 'eur',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_plans');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function plans(): array
    {
        return [
            'starter' => [
                'name' => 'Starter',
                'description' => 'For small businesses getting started.',
                'icon' => 'zap',
                'sort_order' => 1,
                'is_popular' => false,
                'contact_sales' => false,
                'monthly_price_cents' => 4900,
                'yearly_price_cents' => 46800,
                'stripe_monthly_price_id' => null,
                'stripe_yearly_price_id' => null,
                'capabilities' => $this->capabilities([], retentionDays: 90),
                'quota_limits' => $this->quotaLimits(3000, 39000, [
                    'agents_limit' => 2,
                    'pages_limit' => 2000,
                    'minutes_limit' => 500,
                    'links_limit' => 2000,
                    'custom_domains_limit' => 0,
                    'workspace_users_limit' => 5,
                    'teams_limit' => 1,
                ]),
                'highlights' => json_encode([]),
                'extra_features' => json_encode([]),
            ],
            'standard' => [
                'name' => 'Standard',
                'description' => 'For growing teams and businesses.',
                'icon' => 'star',
                'sort_order' => 2,
                'is_popular' => true,
                'contact_sales' => false,
                'monthly_price_cents' => 19900,
                'yearly_price_cents' => 190800,
                'stripe_monthly_price_id' => null,
                'stripe_yearly_price_id' => null,
                'capabilities' => $this->capabilities(
                    ['sentiment', 'handoff', 'api', 'evals', 'guardrails'],
                    retentionDays: 180,
                ),
                'quota_limits' => $this->quotaLimits(15000, 192000, [
                    'agents_limit' => 10,
                    'pages_limit' => 5000,
                    'minutes_limit' => 2000,
                    'links_limit' => 5000,
                    'custom_domains_limit' => 0,
                    'workspace_users_limit' => 20,
                    'teams_limit' => 5,
                ]),
                'highlights' => json_encode([]),
                'extra_features' => json_encode([]),
            ],
            'pro' => [
                'name' => 'Pro',
                'description' => 'For organizations that need it all.',
                'icon' => 'crown',
                'sort_order' => 3,
                'is_popular' => false,
                'contact_sales' => false,
                'monthly_price_cents' => 49900,
                'yearly_price_cents' => 478800,
                'stripe_monthly_price_id' => null,
                'stripe_yearly_price_id' => null,
                'capabilities' => $this->capabilities(self::CAPABILITY_FLAGS, retentionDays: 366),
                'quota_limits' => $this->quotaLimits(50000, 648000, [
                    'agents_limit' => 20,
                    'pages_limit' => 10000,
                    'minutes_limit' => 10000,
                    'links_limit' => 10000,
                    'custom_domains_limit' => 2,
                    'workspace_users_limit' => 100,
                    'teams_limit' => 10,
                ]),
                'highlights' => json_encode(['custom_integrations']),
                'extra_features' => json_encode([]),
            ],
            // Sold through sales only: no Stripe prices, so it never matches a
            // subscription price. Its limits are shown, never granted by checkout.
            'enterprise' => [
                'name' => 'Enterprise',
                'description' => 'For organizations with security, compliance, and scale needs.',
                'icon' => 'building',
                'sort_order' => 4,
                'is_popular' => false,
                'contact_sales' => true,
                'monthly_price_cents' => 0,
                'yearly_price_cents' => 0,
                'stripe_monthly_price_id' => null,
                'stripe_yearly_price_id' => null,
                'capabilities' => $this->capabilities(self::CAPABILITY_FLAGS, retentionDays: 366),
                'quota_limits' => $this->quotaLimits(200000, 2400000, [
                    'agents_limit' => 100,
                    'pages_limit' => 100000,
                    'minutes_limit' => 100000,
                    'links_limit' => 100000,
                    'custom_domains_limit' => 10,
                    'workspace_users_limit' => 1000,
                    'teams_limit' => 100,
                ]),
                'highlights' => json_encode(['custom_integrations', 'eu_data_residency', 'sso_saml', 'uptime_sla', 'dedicated_support', 'custom_contract']),
                'extra_features' => json_encode([]),
            ],
        ];
    }

    /**
     * @param  list<string>  $enabled
     */
    private function capabilities(array $enabled, int $retentionDays): string
    {
        $flags = array_fill_keys(self::CAPABILITY_FLAGS, false);

        foreach ($enabled as $flag) {
            $flags[$flag] = true;
        }

        return json_encode([...$flags, 'retentionDays' => $retentionDays]);
    }

    /**
     * @param  array<string, int>  $fixed
     */
    private function quotaLimits(int $monthlyMessages, int $yearlyMessages, array $fixed): string
    {
        return json_encode([
            'monthly' => ['messages_limit' => $monthlyMessages],
            'yearly' => ['messages_limit' => $yearlyMessages],
            'fixed' => $fixed,
        ]);
    }
};
