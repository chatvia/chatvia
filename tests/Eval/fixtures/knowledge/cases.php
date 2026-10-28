<?php

declare(strict_types=1);

/**
 * Labelled questions for the knowledge retrieval eval (see ../../README.md).
 *
 * - `expect`: text that must be in a retrieved chunk for the question to count
 *   as answered (case-insensitive). Null for a question the knowledge does
 *   not cover: nothing should come back for it.
 * - `history`: earlier user messages, for a follow-up question.
 * - `kind` => `multi-hop` or `global`, with `expect` a list of facts: every
 *   fact must be in a retrieved chunk for the question to count as fully
 *   answered. A fact is a string, or a list of strings where any one will do
 *   (the same fact worded differently in two documents). A multi-hop
 *   question joins facts that sit in different chunks; a global one asks
 *   about the whole collection, so the facts are spread wide.
 *
 * The documents are resources/admin/samples/chunking/{markdown.md,text.txt,transcript.txt}
 * and fixtures/knowledge/{arabic.md,catalogue.md,terms.txt,trips.md,riyadh.md}:
 * one made-up outdoor shop, so questions can join facts across documents.
 */
return [
    // The help centre (Markdown).
    ['query' => 'When are the shops closed?', 'expect' => 'closed on public holidays'],
    ['query' => 'What are the opening hours of the airport shop?', 'expect' => '7:00 to 22:00'],
    ['query' => 'How long can I still change or cancel my order?', 'expect' => 'one hour after you place it'],
    ['query' => 'Which payment methods do you accept?', 'expect' => 'Visa, Mastercard, American Express'],
    ['query' => 'When do you charge my card?', 'expect' => 'when the order leaves the warehouse'],
    ['query' => 'How long does express delivery take?', 'expect' => '1 to 2 working days'],
    ['query' => 'How much is standard shipping?', 'expect' => '4.95, free from 60'],
    ['query' => 'Do I have to pay customs fees when I order to Switzerland?', 'expect' => 'we pay the customs fees for you'],
    ['query' => 'How can I follow my parcel?', 'expect' => 'tracking link'],
    ['query' => 'Is there an API to check order status?', 'expect' => 'api.pinewood.example'],
    ['query' => 'What does it cost to replace the zip on a tent door?', 'expect' => 'New zip on a tent door | 35'],
    ['query' => 'How should I wash a down sleeping bag?', 'expect' => 'special down soap'],
    ['query' => 'How long is the warranty on a backpack?', 'expect' => 'Tents and backpacks have a warranty of five years'],
    ['query' => 'How much is it to rent a two-person tent for a weekend?', 'expect' => 'Two-person tent | 25'],
    ['query' => 'How long is the password reset link valid?', 'expect' => 'The link works for one hour'],
    ['query' => 'How often do you send the newsletter?', 'expect' => 'once a month'],
    ['query' => 'What does an order number look like?', 'expect' => 'PW-104233'],
    ['query' => 'Can my company pay by invoice?', 'expect' => 'pay by invoice'],
    ['query' => 'When can I reach you by phone?', 'expect' => 'Monday to Friday, 9:00 to 17:00'],
    ['query' => 'My rain jacket no longer repels water, what can I do?', 'expect' => 'spray-on proofing'],

    // The policy text (plain text).
    ['query' => 'Can I bring an online order back to a shop?', 'expect' => 'bring it back to any shop'],
    ['query' => 'How fast is a PayPal refund?', 'expect' => 'usually arrives within one day'],
    ['query' => 'Can I swap a jacket for another size?', 'expect' => 'We do not exchange items directly'],
    ['query' => 'My tent got mould, is that covered?', 'expect' => 'got mould is not covered'],
    ['query' => 'How long do repairs take in the summer?', 'expect' => 'up to three weeks'],
    ['query' => 'Where can I drop off a product for repair?', 'expect' => 'next to the workshop'],
    ['query' => 'Does the warranty cover a faded colour?', 'expect' => 'faded colour'],

    // The video transcript.
    ['query' => 'How many pegs come with the Trail 2 tent?', 'expect' => 'There should be twelve'],
    ['query' => 'At what angle should tent pegs go in?', 'expect' => 'forty-five degrees'],
    ['query' => 'Why must the fly not touch the inner tent?', 'expect' => 'water from the outside can come through'],
    ['query' => 'What is in the Trail 2 repair kit?', 'expect' => 'pole sleeve'],
    ['query' => 'How far from the tent should the guy lines be pegged?', 'expect' => 'about one metre'],

    // The Arabic help page.
    ['query' => 'متى يفتح المتجر يوم الجمعة؟', 'expect' => 'الساعة الرابعة عصراً'],
    ['query' => 'كم رسوم التوصيل؟', 'expect' => '٢٥ ريالاً'],
    ['query' => 'هل يمكنني الدفع عند الاستلام؟', 'expect' => 'الدفع عند الاستلام برسوم'],
    ['query' => 'كم يوماً لدي لإرجاع المنتج؟', 'expect' => 'خلال ١٤ يوماً'],
    ['query' => 'Do you accept Apple Pay in the Riyadh store?', 'expect' => 'آبل باي'],

    // The product catalogue (Markdown). Several products look alike (Trail 1,
    // 2 and 3; five sleeping bags), so the ranking has to tell them apart.
    ['query' => 'How heavy is the Trail 3?', 'expect' => '2.9 kg'],
    ['query' => 'How many pegs come with the Basecamp 4?', 'expect' => '| Pegs | 18 |'],
    ['query' => 'What does the Summit 2 cost?', 'expect' => '| Price | 749 |'],
    ['query' => 'What is the comfort temperature of the Ember -18?', 'expect' => 'Goose down, 800 fill power | -11 °C'],
    ['query' => 'How much warmer does a silk liner make my sleeping bag?', 'expect' => 'adds about 2 °C'],
    ['query' => 'Which sleeping mat do I need for camping on snow?', 'expect' => 'On snow, choose 5 or more'],
    ['query' => 'Why should I not blow up my air mat with my mouth?', 'expect' => 'Breath brings moisture into the mat'],
    ['query' => 'How do I measure my back length for a backpack?', 'expect' => 'from the bony point at the base of your neck'],
    ['query' => 'Can I order gas cartridges online?', 'expect' => 'Gas cartridges cannot be sent by post'],
    ['query' => 'How much does the Storm Jacket weigh?', 'expect' => 'It weighs 420 g in size M'],
    ['query' => 'Is the down in your sleeping bags taken from live birds?', 'expect' => 'Responsible Down Standard'],
    ['query' => 'Do you sell climbing ropes?', 'expect' => 'We do not sell climbing gear'],
    ['query' => 'How bright is the Glow 600 headlamp?', 'expect' => '600 lumens'],

    // The terms of sale, Trail Club, rental and Second Trail (plain text).
    ['query' => 'How many Trail Club points do I get per euro?', 'expect' => 'You get 1 point for every euro you spend'],
    ['query' => 'How much do I need to spend to become a Gold member?', 'expect' => 'Gold: from 1,500 spent in twelve months'],
    ['query' => 'How long is a Pinewood gift card valid?', 'expect' => 'Gift cards are valid for three years'],
    ['query' => 'When does the summer sale start?', 'expect' => 'first Monday of July'],
    ['query' => 'How much deposit do you take when I rent a tent?', 'expect' => '50 for each tent'],
    ['query' => 'When do I have to bring back a weekend rental?', 'expect' => 'ends on Monday before 12:00'],
    ['query' => 'What do I get if I sell you a jacket that is like new?', 'expect' => 'You get 40% of the current shop price as store credit'],
    ['query' => 'What warranty does second-hand gear have?', 'expect' => 'It has a warranty of one year'],
    ['query' => 'My parcel arrived with a crushed box, what should I do?', 'expect' => 'take a photo of the box before you open it'],
    ['query' => 'Do you match a lower price from another shop?', 'expect' => 'within 14 days after you bought it from us'],

    // The guided trips and courses (Markdown).
    ['query' => 'How many people go on a guided trip?', 'expect' => 'at least 4 and at most 10 people'],
    ['query' => 'How old do I have to be to join a guided trip?', 'expect' => 'The minimum age is 16'],
    ['query' => 'What do I get back if I cancel a trip 20 days before it starts?', 'expect' => 'Between 30 and 14 days, you get 50% back'],
    ['query' => 'When is the free course on pitching a tent?', 'expect' => 'On the first Saturday of every month from April to October'],
    ['query' => 'How much is the map and compass course?', 'expect' => 'City centre shop, then outdoors | 35'],
    ['query' => 'Where does the High route trip start?', 'expect' => 'the train station at Fennhausen'],
    ['query' => 'Does the winter trip go ahead when the avalanche danger is high?', 'expect' => 'avalanche warning level is 3 or higher'],

    // The Riyadh store guide (Arabic Markdown), once asked in English.
    ['query' => 'ما ساعات عمل المتجر في رمضان؟', 'expect' => 'من الساعة الثامنة مساءً حتى الساعة الثانية فجراً'],
    ['query' => 'كم مبلغ التأمين عند استئجار خيمة؟', 'expect' => '٢٠٠ ريال لكل خيمة'],
    ['query' => 'كم يستغرق التوصيل إلى جدة؟', 'expect' => 'من ثلاثة إلى خمسة أيام عمل'],
    ['query' => 'كم سعر تغيير سحاب باب الخيمة؟', 'expect' => 'تغيير سحّاب باب الخيمة | ٧٠ ريالاً'],
    ['query' => 'How do I keep my tent pegged down in sand?', 'expect' => 'أوتاد الرمل العريضة'],

    // Not covered: nothing should come back.
    ['query' => 'hi', 'expect' => null],
    ['query' => 'thanks, bye!', 'expect' => null],
    ['query' => 'What is the capital of France?', 'expect' => null],
    ['query' => 'Write me a poem about the sea', 'expect' => null],
    ['query' => 'مرحبا', 'expect' => null],
    ['query' => 'Who won the football world cup in 2018?', 'expect' => null],
    ['query' => 'How do I reset my wifi router?', 'expect' => null],
    ['query' => 'Can you recommend a good pizza place nearby?', 'expect' => null],
    ['query' => 'How do I change a flat tyre on my car?', 'expect' => null],
    ['query' => 'What will the weather be like in Oslo tomorrow?', 'expect' => null],

    // Follow-ups: only the history says what they are about.
    ['query' => 'and how much is it?', 'history' => ['How long does express delivery take?'], 'expect' => '9.95'],
    ['query' => 'when does it close?', 'history' => ['Tell me about the airport shop'], 'expect' => '7:00 to 22:00'],
    ['query' => 'and if one breaks on a trip?', 'history' => ['How many tent poles does the Trail 2 have?'], 'expect' => 'pole sleeve'],
    ['query' => 'وكم يستغرق؟', 'history' => ['كم رسوم التوصيل؟'], 'expect' => 'خلال يومين إلى أربعة أيام'],

    // Multi-hop: the answer joins facts from two chunks, often through a
    // shared thing (a shop, a product, a person).
    ['query' => 'A pole of my Trail 2 broke on a trip. What can I use right away, and what does the workshop charge to fix it at home?', 'kind' => 'multi-hop', 'expect' => ['pole sleeve', ['Broken tent pole section | 12', 'broken section of a tent pole costs 12']]],
    ['query' => 'Which shop can I leave a product for repair at, and when is that shop open?', 'kind' => 'multi-hop', 'expect' => ['next to the workshop', 'Monday to Saturday, 9:00 to 18:00']],
    ['query' => 'Can I rent a tent at the shop that is open on public holidays?', 'kind' => 'multi-hop', 'expect' => ['except the airport shop', 'Lakeside and city centre shops']],
    ['query' => 'I live in Norway. How fast is express delivery, and do I have to pay customs when it arrives?', 'kind' => 'multi-hop', 'expect' => ['1 to 2 working days', 'we pay the customs fees for you']],
    ['query' => 'How long is the warranty on a down sleeping bag, and how often may I wash it?', 'kind' => 'multi-hop', 'expect' => ['warranty of three years', 'at most once a year']],
    ['query' => 'I packed my Trail 2 wet and now it has mould. Will the warranty cover it, and how should I store the tent from now on?', 'kind' => 'multi-hop', 'expect' => [['got mould is not covered', 'Mould is not covered by the warranty'], 'Store it loosely in a dry place']],
    ['query' => 'What job does the presenter of the Trail 2 video have, and how many people work at Pinewood?', 'kind' => 'multi-hop', 'expect' => ['I work in our test team', 'twenty-four people']],
    ['query' => 'Why should the fly not touch the inner tent, and what does it cost to patch a small hole in the fabric?', 'kind' => 'multi-hop', 'expect' => ['water from the outside can come through', ['Small hole in fabric | from 10', 'small hole in the fabric costs from 10']]],
    ['query' => 'How long does a repair take in the summer, and where do I send the repair form?', 'kind' => 'multi-hop', 'expect' => ['up to three weeks', 'send it to our workshop with the form inside']],
    ['query' => 'How many pegs should the Trail 2 have, and how far from the tent do the guy lines go?', 'kind' => 'multi-hop', 'expect' => ['There should be twelve', 'about one metre']],
    ['query' => 'طلبت بقيمة ١٥٠ ريالاً وسأدفع عند الاستلام، كم أدفع فوق سعر الطلب؟', 'kind' => 'multi-hop', 'expect' => ['٢٥ ريالاً', '١٥ ريالاً']],
    // Across documents: a trip names the gear or the shop, another document
    // has its price or opening hours.
    ['query' => 'What does the tent you lend on the Winter plateau trip cost to buy?', 'kind' => 'multi-hop', 'expect' => ['Summit 2 tent (shared by two people)', '| Price | 749 |']],
    ['query' => 'Which of your sleeping bags is warm enough for the High route, and what does it cost?', 'kind' => 'multi-hop', 'expect' => ['comfort temperature of -4 °C or lower', 'Duck down, 600 fill power | -4 °C']],
    ['query' => 'When does the shop where the First night outdoors trip meets open on Saturdays?', 'kind' => 'multi-hop', 'expect' => ['the Lakeside shop, at 9:00 on Saturday', 'Monday to Saturday, 9:00 to 18:00']],
    ['query' => 'Where can I sell back my old tent, and when is that shop open?', 'kind' => 'multi-hop', 'expect' => ['You can bring Second Trail items to the Lakeside shop only', 'Monday to Saturday, 9:00 to 18:00']],
    ['query' => 'As a Gold member, what do I pay the workshop to replace a broken tent pole section?', 'kind' => 'multi-hop', 'expect' => ['10% off all repairs', ['Broken tent pole section | 12', 'broken section of a tent pole costs 12']]],
    ['query' => 'Where do we meet for the Winter plateau trip, and is that shop open on public holidays?', 'kind' => 'multi-hop', 'expect' => ['the Airport shop, at 7:00 on Thursday', 'except the airport shop']],
    ['query' => 'Is the Lark 0 sleeping bag warm enough for the First night outdoors trip?', 'kind' => 'multi-hop', 'expect' => ['comfort temperature of 5 °C or lower', 'Lark 0 | Synthetic | 5 °C']],
    ['query' => 'What R-value does my mat need for Lakes and ridges, and which of your insulated air mats meets it?', 'kind' => 'multi-hop', 'expect' => ['R-value of at least 3', 'Air with insulation | 4.6']],
    ['query' => 'I rented a two-person tent last weekend. What will a Trail 2 cost me if I buy it now?', 'kind' => 'multi-hop', 'expect' => ['we take the rental price off the buying price', '| Price | 379 |']],
    ['query' => 'As a Silver member, do I pay for standard delivery on a 40 euro order?', 'kind' => 'multi-hop', 'expect' => ['free standard delivery on every order, without the minimum of 60', '4.95, free from 60']],
    ['query' => 'كم أدفع لاستئجار خيمة لشخصين عطلة نهاية الأسبوع مع التأمين؟', 'kind' => 'multi-hop', 'expect' => ['خيمة لشخصين | ٤٥ ريالاً | ٨٥ ريالاً', '٢٠٠ ريال لكل خيمة']],

    // Global: about the whole collection, so the facts are spread over many
    // chunks (more than the model gets for one question).
    ['query' => 'What are all the ways I can get help from or contact Pinewood?', 'kind' => 'global', 'expect' => ['from 8:00 to 22:00', 'through the contact form', '4 Harbour Road', 'write them in the comments']],
    ['query' => 'What does Pinewood do to make its gear last a long time?', 'kind' => 'global', 'expect' => ['we repair what we sell', 'Good care makes your gear last longer', 'build them to last for many seasons', 'We clean and check every item after each rental', 'repair kit']],
    ['query' => 'Tell me everything about tents: warranty, setup, care, rental and repairs.', 'kind' => 'global', 'expect' => ['Tents and backpacks have a warranty of five years', 'forty-five degrees', 'Always dry your tent fully', 'Two-person tent | 25', 'New zip on a tent door']],
    ['query' => 'Give me an overview of all the fees and costs I might pay.', 'kind' => 'global', 'expect' => ['4.95, free from 60', 'the return costs 7.95', ['New zip on a jacket | 25', 'new zip on a jacket costs 25'], 'Two-person tent | 25']],
    ['query' => 'What topics does your help centre cover?', 'kind' => 'global', 'expect' => ['## Orders', '## Delivery', '## Returns and refunds', '## Product care', '## Your account and privacy', '## Gear rental']],
    ['query' => 'What do customers most often need to know about returns, refunds and the warranty?', 'kind' => 'global', 'expect' => ['within 30 days of delivery', 'five to ten working days', 'warranty of three years', 'faded colour']],
    ['query' => 'لخّص لي كل ما يجب أن أعرفه عن متجر الرياض.', 'kind' => 'global', 'expect' => ['من السبت إلى الخميس', 'خلال يومين إلى أربعة أيام', 'خلال ١٤ يوماً', 'آبل باي']],
    ['query' => 'Compare all your tents and their prices for me.', 'kind' => 'global', 'expect' => ['| Price | 289 |', '| Price | 379 |', '| Price | 469 |', '| Price | 649 |', '| Price | 749 |']],
    ['query' => 'What are all the ways I can save money at Pinewood?', 'kind' => 'global', 'expect' => ['You get 1 point for every euro you spend', 'Students get 10% off', 'we take the rental price off the buying price', 'You get 40% of the current shop price as store credit', 'Trip members get 15% off that gear']],
    ['query' => 'Which guided trips do you offer, and what do they cost?', 'kind' => 'global', 'expect' => ['169 per person', '349 per person', '690 per person', '595 per person']],
    ['query' => 'What do I need to know before I rent gear from you?', 'kind' => 'global', 'expect' => ['at least 18 years old', '50 for each tent', 'ends on Monday before 12:00', 'cleaning fee of 15', 'Lakeside and city centre shops']],
    ['query' => 'ما الخدمات التي يقدمها متجر الرياض في موسم التخييم؟', 'kind' => 'global', 'expect' => ['نوفر خدمة الحجز المسبق للتأجير', 'خيمة صحراوية لأربعة أشخاص', 'أساسيات التخييم في البر', 'مدة الإصلاح المعتادة خمسة أيام عمل']],
];
