<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The prices and texts of the add-ons, managed in the admin panel. The kinds of
 * add-on are fixed in code (the `Addon` and `MessageCreditAddon` enums carry
 * their behaviour), so there is one row per enum value and `key` is that value.
 *
 * `legacy_stripe_price_ids` lists every older Stripe price of the add-on, so
 * subscriptions on an old price are still recognised.
 *
 * The six rows are created here, without Stripe prices: an admin sets the
 * prices under Billing → Add-ons, and saving creates them in Stripe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_addons', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            // Display amount in the smallest currency unit, per `billing_unit`.
            $table->unsignedInteger('price_cents')->default(0);
            $table->string('currency', 3);
            $table->string('billing_unit')->nullable();
            $table->string('icon', 32)->nullable();
            // Messages one unit adds (message-credit packs only).
            $table->unsignedInteger('unit_messages')->nullable();

            $table->string('stripe_product_id')->nullable();
            $table->string('stripe_price_id')->nullable();
            $table->jsonb('legacy_stripe_price_ids')->default('[]');

            $table->timestamps();
        });

        $now = now();

        foreach ($this->addons() as $key => $addon) {
            DB::table('billing_addons')->insert([
                ...$addon,
                'key' => $key,
                'is_active' => true,
                'currency' => 'eur',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_addons');
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function addons(): array
    {
        return [
            // Charged off-session with the same Stripe price as Extra Message
            // Credits: the trigger differs (automatic vs. manual), the line item
            // does not.
            'auto_recharge_credits' => [
                'name' => 'Auto Recharge Credits',
                'description' => 'Automatically add credits when your balance falls below a threshold.',
                'price_cents' => 1000,
                'billing_unit' => 'per 1,000 credits',
                'icon' => 'credit-card',
                'unit_messages' => 1000,
                'stripe_price_id' => null,
            ],
            'extra_message_credits' => [
                'name' => 'Extra Message Credits',
                'description' => 'Purchase additional message credits to extend your monthly allowance.',
                'price_cents' => 900,
                'billing_unit' => 'per 1,000 credits/month',
                'icon' => 'message-circle',
                'unit_messages' => 1000,
                'stripe_price_id' => null,
            ],
            'extra_agents' => [
                'name' => 'Extra AI Agents',
                'description' => 'Add more AI agents beyond your plan limit.',
                'price_cents' => 500,
                'billing_unit' => 'per agent/month',
                'icon' => 'bot',
                'unit_messages' => null,
                'stripe_price_id' => null,
            ],
            'custom_domains' => [
                'name' => 'Custom Domain',
                'description' => 'Use your own custom domain for AI agents.',
                'price_cents' => 2900,
                'billing_unit' => 'per domain/month',
                'icon' => 'globe',
                'unit_messages' => null,
                'stripe_price_id' => null,
            ],
            'remove_branding' => [
                'name' => 'Remove Chatvia Branding',
                'description' => 'Hide the "Powered by Chatvia" badge across your widget and hosted chat.',
                'price_cents' => 1900,
                'billing_unit' => 'per month',
                'icon' => 'sparkles',
                'unit_messages' => null,
                'stripe_price_id' => null,
            ],
            'members' => [
                'name' => 'Members',
                'description' => 'Add more team members to your workspace.',
                'price_cents' => 900,
                'billing_unit' => 'per member/month',
                'icon' => 'users',
                'unit_messages' => null,
                'stripe_price_id' => null,
            ],
        ];
    }
};
