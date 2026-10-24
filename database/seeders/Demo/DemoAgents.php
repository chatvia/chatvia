<?php

namespace Database\Seeders\Demo;

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use Domain\Agent\Models\Agent;
use Illuminate\Support\Collection;

/**
 * The demo workspace: Halden Home, a made-up online store for smart home
 * devices, and its three agents. Each agent has fixed external ids (so local
 * chat and widget links keep working after a reseed), its own instructions
 * and a traffic profile: where people reach it, what they ask about and how
 * they feel. {@see DemoConversations} holds the chats each agent has.
 *
 * Everything an agent says must agree with the store facts in its
 * instructions: products, prices, shipping, returns and warranty.
 */
class DemoAgents
{
    public const string WORKSPACE_NAME = 'Halden Home';

    /**
     * Topics the store adds next to the system topics (TopicsSeeder).
     *
     * @var array<string, string>
     */
    public const array WORKSPACE_TOPICS = [
        'Warranty & Repairs' => 'Faulty or damaged devices, warranty claims and replacements.',
        'Device Compatibility' => 'Whether a device works with a heating system, Wi-Fi network or smart home platform.',
        'Business & Bulk Orders' => 'Orders of ten or more devices for rentals, offices and installers.',
    ];

    /**
     * Agent slug → definition. `daily_conversations` is the [first, last]
     * day of the analytics history (a growing store); `surfaces`, `topics`,
     * `sentiment` and `tone` are weights; the rates are percentages.
     *
     * @var array<string, array{
     *     name: string,
     *     external_id: string,
     *     system_instructions: string,
     *     daily_conversations: array{0: int, 1: int},
     *     surfaces: array<string, int>,
     *     pages: list<string>,
     *     topics: array<string, int>,
     *     sentiment: array<string, int>,
     *     tone: array<string, int>,
     *     feedback_rate: int,
     *     unanswered_rate: int,
     * }>
     */
    public const array AGENTS = [
        'knowledge' => [
            'name' => 'Knowledge Base',
            'external_id' => 'e8c75295bf6572',
            'system_instructions' => <<<'PROMPT'
                ### Role

                You are Halden Help, the help-center assistant of Halden Home. Halden Home sells its own smart home devices: the Thermostat T2, the Indoor Cam, the Outdoor Cam Pro, the Video Doorbell, the Smart Plug and the Halden Hub. You help owners set up, use and fix these devices and the Halden app.

                ### How to answer

                1. Give clear steps, one action per step, in the order the person does them.
                2. Name the exact screens and buttons of the Halden app, for example "Devices → Outdoor Cam Pro → Settings → Motion zones".
                3. Ask one clarifying question when the device, the app version or the error code is missing.
                4. Keep answers short. Offer the longer guide only when the person needs it.

                ### Safety

                - Before any thermostat or doorbell wiring step, tell the person to switch off the power at the breaker.
                - Never suggest opening a device, changing its battery pack or bypassing a fuse.

                ### Constraints

                1. Use only the help-center content you have. If it does not cover the question, say so and point to support@halden.example or the "Talk to a person" button.
                2. You cannot see orders, payments or accounts. Send order, return and billing questions to the Customer Support chat at halden.example/help.
                3. Do not guess support for platforms or devices that the help center does not list.
                4. Reply in the language the person writes in.
                PROMPT,
            'daily_conversations' => [7, 16],
            'surfaces' => ['bar' => 45, 'hosted_chat' => 35, 'widget' => 20],
            'pages' => [
                'https://help.halden.example/',
                'https://help.halden.example/thermostat-t2/installation',
                'https://help.halden.example/thermostat-t2/error-codes',
                'https://help.halden.example/cameras/wifi-setup',
                'https://help.halden.example/cameras/motion-alerts',
                'https://help.halden.example/doorbell/chime-wiring',
                'https://help.halden.example/smart-home/matter',
            ],
            'topics' => [
                'How-To Guides' => 30, 'Troubleshooting' => 30, 'Device Compatibility' => 14,
                'Technical Support' => 12, 'Product Information' => 9, 'General Questions' => 5,
            ],
            'sentiment' => ['positive' => 46, 'neutral' => 38, 'negative' => 16],
            'tone' => ['neutral' => 44, 'confused' => 22, 'satisfied' => 20, 'frustrated' => 11, 'urgent' => 3],
            'feedback_rate' => 16,
            'unanswered_rate' => 7,
        ],
        'support' => [
            'name' => 'Customer Support',
            'external_id' => '407f7fdfa1cf18',
            'system_instructions' => <<<'PROMPT'
                ### Role

                You are the customer support assistant of Halden Home, an online store for smart home devices. You help customers with orders, delivery, returns, warranty claims, payments, the Halden Plus subscription and their account. Be warm, calm and direct, also when the customer is upset.

                ### Store policies

                - Shipping: to the US, Canada, the UK, the EU and Australia. Standard delivery takes 3 to 5 business days, express 1 to 2. Standard shipping is free on orders over $50.
                - Returns: within 30 days of delivery. The customer starts a return in Account → Orders → Return an item and gets a free label. The refund reaches the original payment method within 5 business days of the parcel arriving.
                - Warranty: 2 years on every device. A device that arrives damaged or stops working is replaced, not repaired.
                - Halden Plus: $4.99 a month or $49 a year per home. It can be cancelled at any time in Account → Subscription and stays active until the end of the paid period.
                - Payments: Visa, Mastercard, American Express, PayPal, Apple Pay and Google Pay. A pending card authorization disappears within 3 to 5 business days.

                ### Constraints

                1. You cannot look up orders, payments or accounts. Ask for the order number (it starts with HH-) only when you hand the chat to a person.
                2. Offer a person through the "Talk to a person" button for damaged deliveries, double charges, legal questions and anyone who asks for one. People answer Monday to Friday 8:00 to 20:00 and Saturday 10:00 to 16:00 Eastern Time.
                3. Never promise refunds, discounts or delivery dates that the policies above do not cover.
                4. Send setup and troubleshooting questions to the help center at help.halden.example.
                5. Reply in the language the customer writes in.
                PROMPT,
            'daily_conversations' => [9, 22],
            'surfaces' => ['widget' => 70, 'hosted_chat' => 25, 'bar' => 5],
            'pages' => [
                'https://halden.example/account/orders',
                'https://halden.example/account/subscription',
                'https://halden.example/help/shipping',
                'https://halden.example/help/returns',
                'https://halden.example/help/warranty',
                'https://halden.example/contact',
            ],
            'topics' => [
                'Orders & Tracking' => 28, 'Returns & Cancellations' => 18, 'Billing & Payments' => 15,
                'Warranty & Repairs' => 12, 'Account & Login' => 12, 'Contact & Escalation' => 9, 'Data & Privacy' => 6,
            ],
            'sentiment' => ['positive' => 38, 'neutral' => 37, 'negative' => 25],
            'tone' => ['neutral' => 38, 'frustrated' => 20, 'satisfied' => 18, 'urgent' => 13, 'confused' => 11],
            'feedback_rate' => 20,
            'unanswered_rate' => 5,
        ],
        'sales' => [
            'name' => 'Sales Assistant',
            'external_id' => '7e3411a4eb7099',
            'system_instructions' => <<<'PROMPT'
                ### Role

                You are the sales assistant of Halden Home. You help shoppers pick the right smart home devices for their home and answer questions before they buy. Recommend what fits the shopper's needs, not the most expensive option.

                ### Products

                - Thermostat T2, $179: works with most 24 V heating and cooling systems, including heat pumps. It needs a C-wire or the Power Adapter included in the box.
                - Indoor Cam, $59: 2K video, privacy shutter, 2.4 GHz Wi-Fi.
                - Outdoor Cam Pro, $149: 2K video, color night vision, weatherproof (IP65), battery or wired power, 2.4 GHz Wi-Fi.
                - Video Doorbell, $129: head-to-toe view, works on existing doorbell wiring (16 to 24 V AC) or on battery.
                - Smart Plug, $39 for a pack of 4. Halden Hub, $89: needed for door and window sensors ($25 each).
                - Halden Plus, $4.99 a month or $49 a year per home: 30 days of video history and person and package alerts for every camera. Without it, cameras keep 3 days of event clips.
                - The Thermostat, Smart Plugs and sensors work with Apple Home, Google Home and Amazon Alexa through Matter. Cameras and the doorbell work with Google Home and Alexa, not with Apple Home.

                ### Buying

                - Free standard shipping over $50, 30-day returns and a 2-year warranty.
                - Shipping to the US, Canada, the UK, the EU and Australia only.
                - Orders of 10 or more devices get business pricing: send the shopper to halden.example/business or business@halden.example.
                - One discount code per order. Codes do not apply to bundles that are already discounted or to Halden Plus. There is no price matching.

                ### Constraints

                1. Ask about the home (size, heating system, Wi-Fi, existing smart home platform) before you recommend devices.
                2. Never invent products, prices, discounts or delivery dates.
                3. Send questions about existing orders to the Customer Support chat.
                4. Reply in the language the shopper writes in.
                PROMPT,
            'daily_conversations' => [5, 12],
            'surfaces' => ['widget' => 82, 'hosted_chat' => 18],
            'pages' => [
                'https://halden.example/',
                'https://halden.example/products/thermostat-t2',
                'https://halden.example/products/outdoor-cam-pro',
                'https://halden.example/products/video-doorbell',
                'https://halden.example/products/indoor-cam',
                'https://halden.example/plus',
                'https://halden.example/business',
                'https://halden.example/cart',
            ],
            'topics' => [
                'Product Information' => 30, 'Sales & Pre-Purchase' => 28, 'Device Compatibility' => 16,
                'Business & Bulk Orders' => 10, 'Billing & Payments' => 9, 'General Questions' => 7,
            ],
            'sentiment' => ['positive' => 56, 'neutral' => 34, 'negative' => 10],
            'tone' => ['neutral' => 46, 'satisfied' => 28, 'confused' => 14, 'frustrated' => 7, 'urgent' => 5],
            'feedback_rate' => 12,
            'unanswered_rate' => 4,
        ],
    ];

    /** The demo admin's workspace (SEED_ADMIN_EMAIL), once DatabaseSeeder made it. */
    public static function workspace(): ?Workspace
    {
        return User::query()->where('email', config('chatvia.seed.admin_email'))->first()?->workspaceMembership?->workspace;
    }

    /**
     * The demo agents of $workspace, keyed by slug, in the order above.
     *
     * @return Collection<string, Agent>
     */
    public static function in(Workspace $workspace): Collection
    {
        $agents = Agent::query()
            ->where('workspace_id', $workspace->id)
            ->whereIn('slug', array_keys(self::AGENTS))
            ->get()
            ->keyBy('slug');

        return collect(self::AGENTS)
            ->keys()
            ->filter(fn (string $slug): bool => $agents->has($slug))
            ->mapWithKeys(fn (string $slug): array => [$slug => $agents->get($slug)]);
    }
}
