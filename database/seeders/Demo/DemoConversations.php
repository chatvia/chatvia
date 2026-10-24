<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

/**
 * The chats of the demo agents, per agent slug. Every reply follows the store
 * facts in the agent's instructions ({@see DemoAgents}); every topic is a
 * system topic or one of {@see DemoAgents::WORKSPACE_TOPICS}.
 *
 * A visitor message carries the sentiment and tone the message analysis
 * would give it; the conversation carries the overall ones. An AI reply may
 * carry the visitor's `feedback` and `answered: false` when the knowledge
 * base did not cover the question. `language` (default `en`) and
 * `countries` keep a chat in German from coming from Texas.
 *
 * @phpstan-type DemoMessage array{role: 'user'|'assistant', content: string, sentiment?: string, tone?: string, feedback?: 'positive'|'negative', answered?: bool}
 * @phpstan-type DemoConversation array{title: string, topic: string, sentiment: string, tone: string, language?: string, countries?: list<string>, messages: list<DemoMessage>}
 */
class DemoConversations
{
    /** @var array<string, list<DemoConversation>> */
    public const array SCENARIOS = [
        'knowledge' => [
            [
                'title' => 'Installing the thermostat without a C-wire',
                'topic' => 'How-To Guides',
                'sentiment' => 'positive',
                'tone' => 'satisfied',
                'messages' => [
                    ['role' => 'user', 'content' => "I'm replacing my old Honeywell with the Thermostat T2. There are only 4 wires: R, W, G and Y. No C wire. Can I still install it?", 'sentiment' => 'neutral', 'tone' => 'confused'],
                    ['role' => 'assistant', 'content' => "Yes, you can. The T2 comes with a Power Adapter for homes without a C-wire.\n\n1. Switch off the power to your heating system at the breaker.\n2. Connect R, W, G and Y to the matching terminals on the T2 base.\n3. At the furnace, connect the Power Adapter to the R, W, G and Y terminals and to the C terminal, following the diagram in the box.\n4. Switch the power back on and follow the setup in the Halden app.\n\nDo you have access to the furnace control board?"],
                    ['role' => 'user', 'content' => 'Yes, the furnace is in the basement. Is the adapter the small white box in the package?', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                    ['role' => 'assistant', 'content' => "That's the one. It's labelled \"Power Adapter\" on the back. The furnace end has five short wires with labels. Take a photo of the furnace board before you disconnect anything, so you can go back to the old setup if you need to.", 'feedback' => 'positive'],
                    ['role' => 'user', 'content' => 'Done, the thermostat turned on and the app found it. Thanks!', 'sentiment' => 'positive', 'tone' => 'satisfied'],
                    ['role' => 'assistant', 'content' => 'Great to hear! In the app, go to Devices → Thermostat T2 → Schedule to set your first heating schedule. Enjoy your new thermostat.'],
                ],
            ],
            [
                'title' => 'Outdoor Cam Pro will not connect to Wi-Fi',
                'topic' => 'Troubleshooting',
                'sentiment' => 'positive',
                'tone' => 'frustrated',
                'messages' => [
                    ['role' => 'user', 'content' => "The Outdoor Cam Pro keeps failing at \"Connecting to Wi-Fi\". I've tried 5 times. The password is right.", 'sentiment' => 'negative', 'tone' => 'frustrated'],
                    ['role' => 'assistant', 'content' => "Sorry about that, let's sort it out. The most common cause is the Wi-Fi band: the camera only connects to 2.4 GHz networks.\n\nDoes your router show two networks, for example \"Home\" and \"Home-5G\"? Or one network name for both bands?"],
                    ['role' => 'user', 'content' => "Just one name. It's a mesh system from my internet provider.", 'sentiment' => 'neutral', 'tone' => 'neutral'],
                    ['role' => 'assistant', 'content' => "Mesh systems often steer the phone to 5 GHz during setup, and the camera then can't follow.\n\n1. In your router app, turn on a separate 2.4 GHz network for a few minutes (often called \"IoT network\" or \"band steering off\").\n2. Connect your phone to that network.\n3. Hold the camera's reset button for 10 seconds until the light blinks blue.\n4. Run the setup again in the Halden app.\n\nYou can switch band steering back on after the camera is connected.", 'feedback' => 'positive'],
                    ['role' => 'user', 'content' => 'That worked. It connected on the first try with the IoT network.', 'sentiment' => 'positive', 'tone' => 'satisfied'],
                    ['role' => 'assistant', 'content' => 'Glad it worked! Keep the camera on that network: it reconnects by itself after power cuts or router restarts.'],
                ],
            ],
            [
                'title' => 'Adding the thermostat to Apple Home',
                'topic' => 'How-To Guides',
                'sentiment' => 'neutral',
                'tone' => 'neutral',
                'messages' => [
                    ['role' => 'user', 'content' => 'How do I add the T2 to Apple Home? I already set it up in the Halden app.', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                    ['role' => 'assistant', 'content' => "The T2 joins Apple Home through Matter.\n\n1. In the Halden app, open Devices → Thermostat T2 → Settings → Matter → Pair with another app.\n2. The app shows a pairing code and a QR code.\n3. In the Home app on your iPhone, tap + → Add Accessory and scan the QR code.\n\nYou need an Apple TV or HomePod as a home hub for Matter devices.", 'feedback' => 'positive'],
                    ['role' => 'user', 'content' => 'Will the schedule I made in the Halden app still work?', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                    ['role' => 'assistant', 'content' => 'Yes. The schedule runs on the thermostat itself, so it keeps working. If you also make automations in Apple Home, the last change wins, so avoid two schedules for the same hours.'],
                ],
            ],
            [
                'title' => 'Too many motion alerts from the doorbell',
                'topic' => 'Troubleshooting',
                'sentiment' => 'neutral',
                'tone' => 'neutral',
                'messages' => [
                    ['role' => 'user', 'content' => 'My Video Doorbell sends me an alert every time a car drives past. I get like 40 notifications a day.', 'sentiment' => 'negative', 'tone' => 'frustrated'],
                    ['role' => 'assistant', 'content' => "That's a lot. Two settings fix this in most homes:\n\n1. **Motion zones**: Devices → Video Doorbell → Settings → Motion zones. Draw the zone around your path and porch, and leave the street out.\n2. **Sensitivity**: in the same screen, move the slider to Medium.\n\nWith Halden Plus you can also turn on Person alerts only, so cars never notify you.", 'feedback' => 'positive'],
                    ['role' => 'user', 'content' => "OK I drew the zone. I'll see how it goes tomorrow.", 'sentiment' => 'neutral', 'tone' => 'neutral'],
                    ['role' => 'assistant', 'content' => 'Sounds good. If you still get street alerts, make the zone a little smaller at the bottom edge: that is where passing cars usually enter the picture.'],
                ],
            ],
            [
                'title' => 'Using the Indoor Cam with Home Assistant',
                'topic' => 'Device Compatibility',
                'sentiment' => 'negative',
                'tone' => 'neutral',
                'messages' => [
                    ['role' => 'user', 'content' => 'Does the Indoor Cam have an RTSP stream? I want to add it to Home Assistant.', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                    ['role' => 'assistant', 'content' => "I don't have information about RTSP streams or Home Assistant for the Indoor Cam. The help center lists Google Home and Amazon Alexa as the supported platforms for cameras.\n\nOur support team can tell you more: use the \"Talk to a person\" button or write to support@halden.example.", 'feedback' => 'negative', 'answered' => false],
                    ['role' => 'user', 'content' => "That's a shame. Local streaming was the main reason I looked at it.", 'sentiment' => 'negative', 'tone' => 'neutral'],
                    ['role' => 'assistant', 'content' => "I understand. I've noted that local streaming matters to you. The support team can say whether it is planned."],
                ],
            ],
            [
                'title' => 'Thermostat zeigt Fehler E4',
                'topic' => 'Troubleshooting',
                'sentiment' => 'positive',
                'tone' => 'confused',
                'language' => 'de',
                'countries' => ['de'],
                'messages' => [
                    ['role' => 'user', 'content' => 'Mein Thermostat T2 zeigt seit heute Morgen „E4“ an und heizt nicht mehr. Was bedeutet das?', 'sentiment' => 'negative', 'tone' => 'confused'],
                    ['role' => 'assistant', 'content' => "E4 bedeutet, dass das Thermostat keinen stabilen Strom bekommt.\n\n1. Schalten Sie die Heizung an der Sicherung aus.\n2. Nehmen Sie das Thermostat von der Wandhalterung und prüfen Sie, ob das C-Kabel (oder das Kabel des Netzteils) fest in der Klemme sitzt.\n3. Setzen Sie das Thermostat wieder auf und schalten Sie die Sicherung ein.\n\nHaben Sie in den letzten Tagen etwas an der Heizung geändert?"],
                    ['role' => 'user', 'content' => 'Der Heizungsmonteur war gestern da. Das C-Kabel war tatsächlich locker. Jetzt läuft es wieder, danke!', 'sentiment' => 'positive', 'tone' => 'satisfied'],
                    ['role' => 'assistant', 'content' => 'Sehr gut, das freut mich! Falls E4 noch einmal erscheint, prüfen Sie zuerst dieses Kabel. Einen schönen Tag noch!', 'feedback' => 'positive'],
                ],
            ],
        ],

        'support' => [
            [
                'title' => 'Order not delivered yet',
                'topic' => 'Orders & Tracking',
                'sentiment' => 'neutral',
                'tone' => 'confused',
                'messages' => [
                    ['role' => 'user', 'content' => 'I ordered a doorbell last Tuesday and it still says "Shipped". When will it arrive?', 'sentiment' => 'neutral', 'tone' => 'confused'],
                    ['role' => 'assistant', 'content' => "Standard delivery takes 3 to 5 business days after the order ships, so an order from last Tuesday is usually there by this Tuesday or Wednesday.\n\nYou can follow the parcel in Account → Orders → Track package. That page shows the carrier's latest scan."],
                    ['role' => 'user', 'content' => 'The tracking page says "Arriving tomorrow". I just missed that link. Thanks.', 'sentiment' => 'positive', 'tone' => 'satisfied'],
                    ['role' => 'assistant', 'content' => "Good news! If it doesn't arrive by the end of tomorrow, come back here and I'll connect you with the team so they can contact the carrier.", 'feedback' => 'positive'],
                ],
            ],
            [
                'title' => 'Returning a pack of smart plugs',
                'topic' => 'Returns & Cancellations',
                'sentiment' => 'positive',
                'tone' => 'neutral',
                'messages' => [
                    ['role' => 'user', 'content' => "I bought the 4-pack of smart plugs but I only need two. Can I return the pack? I've opened it.", 'sentiment' => 'neutral', 'tone' => 'neutral'],
                    ['role' => 'assistant', 'content' => "Yes. You can return it within 30 days of delivery, also when the box is opened.\n\n1. Go to Account → Orders and pick the order.\n2. Choose Return an item and select the Smart Plug 4-pack.\n3. Print the free return label and drop the parcel at the carrier.\n\nThe refund reaches your original payment method within 5 business days after the parcel arrives with us. The pack is returned as a whole, not plug by plug.", 'feedback' => 'positive'],
                    ['role' => 'user', 'content' => 'Perfect, I just printed the label. Easy.', 'sentiment' => 'positive', 'tone' => 'satisfied'],
                    ['role' => 'assistant', 'content' => "Great! You'll get an email when the parcel arrives and another one when the refund is on its way."],
                ],
            ],
            [
                'title' => 'Video Doorbell arrived cracked',
                'topic' => 'Warranty & Repairs',
                'sentiment' => 'negative',
                'tone' => 'frustrated',
                'messages' => [
                    ['role' => 'user', 'content' => 'The doorbell I got today has a crack across the camera glass. I took the day off to install it. Really annoying.', 'sentiment' => 'negative', 'tone' => 'frustrated'],
                    ['role' => 'assistant', 'content' => "I'm really sorry, that's not how your new doorbell should arrive, and I know you planned your day around it.\n\nA device that arrives damaged is replaced under warranty, at no cost to you. I'll hand you to a person on our team so they can send the replacement. Could you share your order number (it starts with HH-) and a photo of the crack?"],
                    ['role' => 'user', 'content' => 'HH-204817. Can they ship it express at least?', 'sentiment' => 'negative', 'tone' => 'urgent'],
                    ['role' => 'assistant', 'content' => "I've passed on your order number and your request for express shipping. A person from the team will reply here shortly; they answer until 20:00 Eastern Time on weekdays. You don't need to send the cracked doorbell back before the new one arrives.", 'feedback' => 'positive'],
                    ['role' => 'user', 'content' => 'OK. Thanks for being quick at least.', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                    ['role' => 'assistant', 'content' => "Of course. Once the replacement ships, you'll get the tracking link by email."],
                ],
            ],
            [
                'title' => 'Charged twice for Halden Plus',
                'topic' => 'Billing & Payments',
                'sentiment' => 'neutral',
                'tone' => 'urgent',
                'messages' => [
                    ['role' => 'user', 'content' => 'I see two charges of $49 from Halden on my card today. I only subscribed to Plus once!', 'sentiment' => 'negative', 'tone' => 'urgent'],
                    ['role' => 'assistant', 'content' => "I understand the worry. Often one of the two is a pending authorization: your bank holds the amount while the payment is checked, and the hold disappears within 3 to 5 business days without being charged.\n\nIn your banking app, does one of the two show as \"pending\"?"],
                    ['role' => 'user', 'content' => 'Yes, one says pending and one is posted.', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                    ['role' => 'assistant', 'content' => "Then you've only been charged once. The pending one will drop off by itself. If it is still there after 5 business days, write to us here and a person will check it with the payment provider.", 'feedback' => 'positive'],
                    ['role' => 'user', 'content' => 'Got it, I will wait then.', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                    ['role' => 'assistant', 'content' => 'Thanks for checking. Your Halden Plus is active for the full year.'],
                ],
            ],
            [
                'title' => 'Password reset email not arriving',
                'topic' => 'Account & Login',
                'sentiment' => 'neutral',
                'tone' => 'confused',
                'messages' => [
                    ['role' => 'user', 'content' => "I clicked \"Forgot password\" three times and I'm not getting any email.", 'sentiment' => 'negative', 'tone' => 'confused'],
                    ['role' => 'assistant', 'content' => "Let's check a few things:\n\n1. Look in your spam or promotions folder for an email from no-reply@halden.example.\n2. Make sure you use the email address of your Halden account; it can differ from the one on your order.\n3. Wait 10 minutes before you ask again: each new request cancels the previous link.\n\nIf none of this helps, I can hand you to a person who can check your account."],
                    ['role' => 'user', 'content' => 'It was in the promotions tab. Logged in now.', 'sentiment' => 'positive', 'tone' => 'satisfied'],
                    ['role' => 'assistant', 'content' => 'Glad you are back in! Add no-reply@halden.example to your contacts so our emails land in your inbox next time.', 'feedback' => 'positive'],
                ],
            ],
            [
                'title' => 'Deleting my account and recordings',
                'topic' => 'Data & Privacy',
                'sentiment' => 'neutral',
                'tone' => 'neutral',
                'messages' => [
                    ['role' => 'user', 'content' => "I'm moving out and selling the house. How do I delete my account and all camera recordings?", 'sentiment' => 'neutral', 'tone' => 'neutral'],
                    ['role' => 'assistant', 'content' => "Before you delete the account, remove the devices so the new owners can set them up:\n\n1. In the app, open each device → Settings → Remove device.\n2. Cancel Halden Plus in Account → Subscription if you have it.\n3. Go to Account → Privacy → Delete account.\n\nRecordings are deleted at once. The rest of your account data is removed within 30 days, and you get an email when it is done."],
                    ['role' => 'user', 'content' => 'Do I get a refund for the rest of my Plus year?', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                    ['role' => 'assistant', 'content' => "Halden Plus stays active until the end of the paid period and is not refunded for the remaining months. A person on our team can look at your case if you'd like: use the \"Talk to a person\" button.", 'feedback' => 'negative'],
                ],
            ],
        ],

        'sales' => [
            [
                'title' => 'Camera for a backyard without an outlet',
                'topic' => 'Product Information',
                'sentiment' => 'positive',
                'tone' => 'satisfied',
                'messages' => [
                    ['role' => 'user', 'content' => "I want a camera for the back of the garden. There's no power outlet out there. What would you suggest?", 'sentiment' => 'neutral', 'tone' => 'neutral'],
                    ['role' => 'assistant', 'content' => "The Outdoor Cam Pro ($149) fits well: it runs on its built-in battery, is weatherproof and has color night vision.\n\nTwo questions so I can be sure:\n1. How far is the spot from your router? The camera needs 2.4 GHz Wi-Fi there.\n2. Do you want to keep video for longer than 3 days?"],
                    ['role' => 'user', 'content' => 'About 20 meters from the house. My phone has good signal there. 3 days of clips is fine for me.', 'sentiment' => 'positive', 'tone' => 'neutral'],
                    ['role' => 'assistant', 'content' => "Then the Outdoor Cam Pro on its own is all you need, no Halden Plus required. In a spot with normal activity, the battery lasts about 3 months between charges.\n\nYou also get free shipping, since the order is over $50.", 'feedback' => 'positive'],
                    ['role' => 'user', 'content' => 'Sounds good, adding it to my cart.', 'sentiment' => 'positive', 'tone' => 'satisfied'],
                    ['role' => 'assistant', 'content' => "Great choice! If the signal turns out weak once it's mounted, you have 30 days to return it."],
                ],
            ],
            [
                'title' => 'Does the T2 work with a heat pump?',
                'topic' => 'Device Compatibility',
                'sentiment' => 'positive',
                'tone' => 'neutral',
                'messages' => [
                    ['role' => 'user', 'content' => 'We have a heat pump with electric backup heat. Will the Thermostat T2 work?', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                    ['role' => 'assistant', 'content' => "Yes, the T2 supports heat pumps with auxiliary (backup) heat on 24 V systems.\n\nA quick check: look at your current thermostat's wires. If you see an O or B wire and an AUX or W2 wire, you're set. Wires marked with 120 V or 240 V (thick wires, often on baseboard heaters) won't work with the T2."],
                    ['role' => 'user', 'content' => 'I see R, C, Y, G, O and W2. Thin wires.', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                    ['role' => 'assistant', 'content' => "That's a standard 24 V heat pump setup with a C-wire, so the T2 works and you won't need the Power Adapter. The app asks about the O/B wire during setup.", 'feedback' => 'positive'],
                    ['role' => 'user', 'content' => 'Perfect, thanks for the clear answer.', 'sentiment' => 'positive', 'tone' => 'satisfied'],
                    ['role' => 'assistant', 'content' => "You're welcome! Enjoy the T2."],
                ],
            ],
            [
                'title' => 'Equipping 24 rental apartments',
                'topic' => 'Business & Bulk Orders',
                'sentiment' => 'positive',
                'tone' => 'neutral',
                'messages' => [
                    ['role' => 'user', 'content' => 'I manage 24 rental apartments and want a thermostat and a smart plug in each. Do you offer a discount?', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                    ['role' => 'assistant', 'content' => "Yes. Orders of 10 or more devices get business pricing. For 24 thermostats and 24 plugs, our business team will send you a quote.\n\nYou can request it at halden.example/business or write to business@halden.example. It helps to mention:\n- the number of units per device\n- the delivery address (one or several)\n- whether tenants or you will manage the devices"],
                    ['role' => 'user', 'content' => 'I would manage them. Can I control all apartments from one account?', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                    ['role' => 'assistant', 'content' => 'Yes. In the Halden app you can add one home per apartment and switch between them in one account. You can also invite a tenant to a single home, so they only see their own thermostat.', 'feedback' => 'positive'],
                    ['role' => 'user', 'content' => "Great, I'll fill in the business form today.", 'sentiment' => 'positive', 'tone' => 'satisfied'],
                    ['role' => 'assistant', 'content' => 'Sounds good. The business team usually replies within one business day.'],
                ],
            ],
            [
                'title' => 'Do I need Halden Plus?',
                'topic' => 'Sales & Pre-Purchase',
                'sentiment' => 'neutral',
                'tone' => 'neutral',
                'messages' => [
                    ['role' => 'user', 'content' => 'Is Halden Plus required for the doorbell to work?', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                    ['role' => 'assistant', 'content' => "No. Without Plus, the doorbell gives you live view, two-way talk, motion and ring alerts, and keeps event clips for 3 days.\n\nHalden Plus ($4.99 a month or $49 a year per home) adds 30 days of video history and person and package alerts, for all your cameras together."],
                    ['role' => 'user', 'content' => 'So if I have 3 cameras I pay once?', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                    ['role' => 'assistant', 'content' => 'Exactly, one subscription covers every camera and doorbell in the home.', 'feedback' => 'positive'],
                ],
            ],
            [
                'title' => 'Discount code not working on a bundle',
                'topic' => 'Billing & Payments',
                'sentiment' => 'negative',
                'tone' => 'frustrated',
                'messages' => [
                    ['role' => 'user', 'content' => 'Your newsletter gave me WELCOME10 but the cart says it is not valid for the Starter Security Bundle. What is the point then?', 'sentiment' => 'negative', 'tone' => 'frustrated'],
                    ['role' => 'assistant', 'content' => "I see why that's frustrating. Discount codes don't apply to bundles that already have a discount, and the Starter Security Bundle is already priced below the separate devices.\n\nYou can use WELCOME10 on devices bought on their own. Would you like me to compare the bundle with buying the devices separately with the code?"],
                    ['role' => 'user', 'content' => 'Yes, which is cheaper?', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                    ['role' => 'assistant', 'content' => "I don't have the current bundle price, so I can't compare the totals reliably. The bundle page shows how much you save compared with the separate prices; if that saving is more than 10%, the bundle is cheaper.", 'answered' => false, 'feedback' => 'negative'],
                    ['role' => 'user', 'content' => 'Fine, I will check myself.', 'sentiment' => 'negative', 'tone' => 'frustrated'],
                    ['role' => 'assistant', 'content' => "Sorry I couldn't give you the exact numbers. If you have questions about the devices themselves, I'm here."],
                ],
            ],
            [
                'title' => '¿Hacen envíos a México?',
                'topic' => 'Sales & Pre-Purchase',
                'sentiment' => 'neutral',
                'tone' => 'neutral',
                'language' => 'es',
                'countries' => ['mx'],
                'messages' => [
                    ['role' => 'user', 'content' => 'Hola, ¿hacen envíos a México? Me interesa el Outdoor Cam Pro.', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                    ['role' => 'assistant', 'content' => 'Hola, gracias por tu interés. Por ahora solo enviamos a Estados Unidos, Canadá, el Reino Unido, la Unión Europea y Australia, así que no podemos enviar a México.'],
                    ['role' => 'user', 'content' => 'Qué lástima. ¿Y si lo envío a una dirección en Texas?', 'sentiment' => 'negative', 'tone' => 'neutral'],
                    ['role' => 'assistant', 'content' => 'Sí, puedes enviarlo a una dirección en Estados Unidos. Ten en cuenta que la garantía de 2 años y las devoluciones se gestionan solo dentro de los países a los que enviamos.', 'feedback' => 'positive'],
                ],
            ],
        ],
    ];
}
