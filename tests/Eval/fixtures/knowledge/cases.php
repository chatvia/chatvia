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
 * and fixtures/knowledge/{arabic.md,catalogue.md,terms.txt,trips.md,riyadh.md,
 * footwear.md,routes.md,company.txt,jeddah.md}:
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
    // The footwear, poles and water catalogue (Markdown). Six boots share one
    // table layout, so the ranking has to tell them apart.
    ['query' => 'Can I hire walking boots from you for a weekend?', 'expect' => 'You cannot rent boots or poles from us'],
    ['query' => 'What is the price of the Fennick Mid?', 'expect' => '| Price | 139 |'],
    ['query' => 'How much does a pair of Ridgeline Mid boots weigh?', 'expect' => '| Weight, pair | 1,150 g |'],
    ['query' => 'Is there a Gore-Tex style lining in the Ridgeline Leather?', 'expect' => 'None, the leather is waxed'],
    ['query' => 'Which shoe sizes is the Highpass made in?', 'expect' => '| Sizes | 38 to 48 |'],
    ['query' => 'Can I try on hiking boots at the airport before my flight?', 'expect' => 'the only boots in stock in the Airport shop'],
    ['query' => 'How cold can it get before the Frostline stops keeping my feet warm?', 'expect' => 'down to about -30 °C'],
    ['query' => 'My foot is 273 mm long. What EU size should I order?', 'expect' => '| 43 | 9 | 10 | 11 | 273 mm |'],
    ['query' => 'How much space should there be behind my heel when I try boots on?', 'expect' => 'One finger should fit behind your heel'],
    ['query' => 'My toes keep banging the front of my boots on the way down. How should I tie them?', 'expect' => 'wrap the laces around each hook twice'],
    ['query' => 'How many kilometres before full-grain leather boots are comfortable?', 'expect' => 'About 60 km over two to three weeks'],
    ['query' => 'Is it fine to dry soaked boots by the campfire?', 'expect' => 'next to a stove or a camp fire'],
    ['query' => 'What should I use to waterproof suede boots?', 'expect' => 'Nubuck Spray, 200 ml'],
    ['query' => 'How much do you charge to put new soles on my boots?', 'expect' => 'New sole, glued and stitched | 79'],
    ['query' => 'Can I send back boots I already took on a hike?', 'expect' => 'if it is not worn outside'],
    ['query' => 'Are cotton socks OK for hiking?', 'expect' => 'do not wear cotton on walks'],
    ['query' => 'Why would I wear a thin sock under my hiking sock?', 'expect' => 'rub against each other instead of against your skin'],
    ['query' => 'What do the thick knee-length socks cost?', 'expect' => '| Thick | Knee | 26 |'],
    ['query' => 'What keeps grit and little stones out of my trail shoes?', 'expect' => 'small stones and sand get into the boot'],
    ['query' => 'I am 170 cm tall. How long should my walking sticks be?', 'expect' => '| 166 to 175 cm | 115 cm |'],
    ['query' => 'Are carbon trekking poles more fragile than aluminium ones?', 'expect' => 'it can snap when it is hit sideways'],
    ['query' => 'Does a water filter make stream water safe from viruses?', 'expect' => 'A filter does not remove viruses'],
    ['query' => 'My water filter froze in the tent last night. Can I keep using it?', 'expect' => 'Ice cracks the fibres'],
    ['query' => 'Is there paracetamol or ibuprofen in your first aid kits?', 'expect' => 'We do not put medicines such as painkillers in our kits'],
    ['query' => 'Should I pop a small blister on my heel?', 'expect' => 'leave a small, closed blister alone'],

    // Not covered, but close to the footwear, sock and water topics.
    ['query' => 'Do you sell a UV light pen to clean drinking water?', 'expect' => null],
    ['query' => 'Do you have heated socks with batteries?', 'expect' => null],
    ['query' => 'Do you sell rubber wellington boots for the garden?', 'expect' => null],

    // Multi-hop: a trip names the boots it needs, the footwear catalogue has
    // the model and its price; Trail Club gives the repair discount.
    ['query' => 'What boots does the High route trip ask for, and which of your boots is that and what does it cost?', 'kind' => 'multi-hop', 'expect' => ['walking boots with a stiff sole', '| Price | 299 |']],
    ['query' => 'Which of your boots should I bring on the Winter plateau trip, and what does it cost?', 'kind' => 'multi-hop', 'expect' => ['and winter boots', '| Price | 329 |']],
    ['query' => 'As a Gold member, what do I pay the workshop to resole my walking boots?', 'kind' => 'multi-hop', 'expect' => ['10% off all repairs', 'New sole, glued and stitched | 79']],

    // Global: boot care is spread over several sections.
    ['query' => 'What do I need to do to make my hiking boots last for years?', 'kind' => 'global', 'expect' => ['Never take a new pair on a long trip straight from the box', 'next to a stove or a camp fire', 'wax every four to six walking days', 'New sole, glued and stitched | 79', 'Tread that wears down is normal use']],

    // The self-guided routes (Markdown): general sections.
    ['query' => 'Which walkers is a black route meant for?', 'expect' => 'high mountain ground, with scrambling'],
    ['query' => 'What happens if a ranger finds my tent somewhere it is not allowed?', 'expect' => 'fine of 150'],
    ['query' => 'Lightning is close and we are a group of four on a hill. How far apart should we stand?', 'expect' => 'at least fifteen metres apart'],
    ['query' => 'How deep should the hole be when I need a poo in the hills?', 'expect' => '15 to 20 cm deep'],
    ['query' => 'Do I have to sign up to get the route tracks for my phone?', 'expect' => 'You do not need an account'],
    ['query' => 'Is there a reward for telling you about a fallen tree on a trail?', 'expect' => 'gets 50 Trail Club points'],

    // The self-guided routes (Markdown): one route each. The routes look
    // alike, so the ranking has to find the right one.
    ['query' => 'Where can I top up my bottle on the Harbour Loop?', 'expect' => 'drinking tap outside the rowing club'],
    ['query' => 'Is wild camping OK at Grey Tarn?', 'expect' => 'flat grass at the east shore of the tarn'],
    ['query' => 'There is no water on the Three Lakes Ridge, so where do I fill up first?', 'expect' => 'fountain in front of Elsby church'],
    ['query' => 'Will there still be snow on the Kessel Pass path in early summer?', 'expect' => 'until the end of June'],
    ['query' => 'What time is the final train back from Fennhausen after the Kessel Pass hike?', 'expect' => '19:40'],
    ['query' => 'Can my dog come along on the Fennhausen Meadows walk in July?', 'expect' => 'Dogs are not welcome in the meadows'],
    ['query' => 'How tall is the waterfall at Marten Falls?', 'expect' => 'drops 40 metres'],
    ['query' => 'How much water do I need to bring for the Corran Ridge Traverse?', 'expect' => 'Carry at least three litres'],
    ['query' => 'When is the café in Ashdown Woods open?', 'expect' => 'from Wednesday to Sunday, from 10:00 to 18:00'],
    ['query' => 'What is the fare for the boat over to Birchmere?', 'expect' => 'costs 6 for an adult'],
    ['query' => 'Can I hike the Haldane Gorge in March?', 'expect' => 'closed from 1 November to 30 April'],
    ['query' => 'Do I have to bring my own helmet for Haldane Gorge?', 'expect' => 'lend them for free at the hut'],
    ['query' => 'How do I get from Fennhausen to the start of the Wenmoor Plateau Crossing?', 'expect' => 'take bus 90'],
    ['query' => 'Why are there red flags along the Stavely Fort Walk some days?', 'expect' => 'shooting range'],
    ['query' => 'Can I spend the night on top of Larkhope Hill to watch the sun come up?', 'expect' => 'bivvy bag without a tent'],
    ['query' => 'In which months can I not walk the upper part of the Old Mine Trail?', 'expect' => 'closed from November to March'],
    ['query' => 'Is there public transport to the Aldmoor snowshoe loop?', 'expect' => 'winter bus 95'],
    ['query' => 'Can I pitch a tent on the river bank on the way to Elsby?', 'expect' => 'Camping on the banks has been banned'],
    ['query' => 'When is the path to the tip of Osprey Point shut?', 'expect' => 'from 1 April to 31 July'],
    ['query' => 'The scree at the end of the Rothwick Horseshoe looks scary in the rain. Is there another way down?', 'expect' => 'marked with red and blue stripes'],

    // Not covered, though close to the routes guide: nothing should come back.
    ['query' => 'How long is the Silver Lake trail, and which bus goes to its start?', 'expect' => null],
    ['query' => 'Is there a self-guided hut-to-hut route across the Dolomites in your guide?', 'expect' => null],
    ['query' => 'How do I get to the start of the Hollow Crag via ferrata?', 'expect' => null],

    // Multi-hop: a route joined with another chunk of the guide, or with the
    // help centre or the catalogue.
    ['query' => 'Which of your shops is closest to the Marten Falls walk, and is it open on Sundays?', 'kind' => 'multi-hop', 'expect' => ['Airport, at the other end of the bus 7 line', 'Every day, 7:00 to 22:00']],
    ['query' => 'What grade is the Corran Ridge Traverse, and who is that grade meant for?', 'kind' => 'multi-hop', 'expect' => ['Black, for the scramble over the Teeth', 'Only for people who can read a map well']],
    ['query' => 'On the Wenmoor Plateau Crossing, what must I do before I drink from the tarns, and can I order gas for my stove online before I go?', 'kind' => 'multi-hop', 'expect' => ['safe once you boil it or use a filter', 'Gas cartridges cannot be sent by post']],

    // Global: the facts are spread over the whole routes guide.
    ['query' => 'Which of your self-guided routes are easy enough for a family, and how long is each one?', 'kind' => 'global', 'expect' => ['6.5 km, a loop', '7.9 km, a loop', '5.4 km, a loop', '12.6 km, one way', '18.0 km, one way', '7.2 km, there and back']],

    // The business, school and company guide (plain text).
    ['query' => 'We just opened a business account. How much can we owe you on unpaid invoices?', 'expect' => 'starts with a credit limit of 2,500'],
    ['query' => 'What discount does our company get on a single order of 6,000 euros?', 'expect' => 'Tier 2: from 5,000 in one order, 8% off'],
    ['query' => 'How early do I need to ask if my class wants to borrow tents for a school camp?', 'expect' => 'at least six weeks before the trip'],
    ['query' => 'How many tents can our scout group borrow in one go?', 'expect' => 'up to 15 tents, 40 sleeping bags'],
    ['query' => 'What does the school pay if the kids lose some of the borrowed gear?', 'expect' => 'maximum of 300 for one loan'],
    ['query' => 'How big does our hiking club have to be to get a partner agreement?', 'expect' => 'at least 25 paying members'],
    ['query' => 'Can our rangers try on the uniform jackets before we order them?', 'expect' => 'Ask for a size kit'],
    ['query' => 'Can you stitch our logo onto waterproof jackets?', 'expect' => 'We never embroider a waterproof jacket'],
    ['query' => 'Is there a one-off charge to put our company logo on clothing?', 'expect' => 'a setup fee of 45'],
    ['query' => 'How long before our charity trail run should we ask you to sponsor it?', 'expect' => 'at least three months before the event'],
    ['query' => 'How much of the material in your clothes is recycled?', 'expect' => '64% of the fabric in our own clothing'],
    ['query' => 'How big is the carbon footprint of Pinewood?', 'expect' => '1,920 tonnes of CO2 equivalent'],
    ['query' => 'Do you pad your parcels with plastic bubble wrap?', 'expect' => 'never with plastic air cushions or foam'],
    ['query' => 'My old fleece is full of holes and nobody can wear it. Can I give it to you?', 'expect' => 'drop it in the green End of Trail box'],
    ['query' => 'I applied for a job in your shop. When will I get a reply?', 'expect' => 'Within ten working days you hear from us'],
    ['query' => 'Are internships at Pinewood paid?', 'expect' => 'an allowance of 650 a month'],
    ['query' => 'I am a journalist writing about outdoor brands. Who should I talk to?', 'expect' => 'Sofia Marino leads communications'],
    ['query' => 'Can I bring my guide dog into your shops?', 'expect' => 'other assistance dogs are always welcome'],
    ['query' => 'Is there a calm time to visit with my autistic son, without loud music?', 'expect' => 'every Tuesday from 10:00 to 11:00'],
    ['query' => 'What does it cost to have a present wrapped when I order online?', 'expect' => 'for 3.50 per item'],
    ['query' => 'I think I left my phone in the airport shop. Where does it end up?', 'expect' => "airport's own lost and found office"],
    ['query' => 'I am not happy with the reply to my complaint. Who looks at it next?', 'expect' => 'customer service manager, Daniel Okafor'],
    ['query' => 'How long do you keep the CCTV recordings from your shops?', 'expect' => 'We keep the images for 28 days'],
    ['query' => 'Who started Pinewood Outdoor?', 'expect' => 'Anna Lindqvist and Pieter Brandsma'],
    ['query' => 'Can I get into the Lakeside shop in a wheelchair?', 'expect' => 'gentle ramp and an automatic door'],

    // Not covered, but close to what the guide talks about: nothing should come back.
    ['query' => 'Can I open a Pinewood franchise shop in my town?', 'expect' => null],
    ['query' => 'What is the Pinewood share price, and where can I buy shares?', 'expect' => null],
    ['query' => 'How much profit did Pinewood make last year?', 'expect' => null],

    // Multi-hop: the guide joined with the help centre and the catalogue, and
    // two passages of the guide itself.
    ['query' => 'Where do we pick up the tents our school borrows, and what is the street address of that shop?', 'kind' => 'multi-hop', 'expect' => ['at the back door next to the workshop', '4 Harbour Road']],
    ['query' => 'We want our logo on Storm Jackets for our guides. How is the logo put on, and what does one jacket cost?', 'kind' => 'multi-hop', 'expect' => ['always a transfer print', 'It weighs 420 g in size M and costs 249']],
    ['query' => 'How much worn-out gear did the take-back programme collect in 2025, and what do I get for each bag I bring?', 'kind' => 'multi-hop', 'expect' => ['collected 6.3 tonnes of worn-out gear', 'a voucher of 5 for each full bag']],

    // Global: the facts are spread over many passages of the guide.
    ['query' => 'Apart from selling gear, what services do your shops offer visitors?', 'kind' => 'global', 'expect' => ['we wrap presents for free', 'Backpack fitting, 30 minutes', 'every till has a hearing loop', 'into the lost property box', 'Pinewood Gear Swap']],

    // The Jeddah store guide (Arabic Markdown).
    ['query' => 'ما عنوان فرعكم في جدة بالضبط؟', 'expect' => 'مبنى "مرسى النورس"'],
    ['query' => 'هل أدفع رسوماً لركن سيارتي عند زيارة فرع جدة؟', 'expect' => 'الساعات الثلاث الأولى مجانية'],
    ['query' => 'كيف أصل إلى متجر جدة بالمواصلات العامة؟', 'expect' => 'الحافلة رقم ٧'],
    ['query' => 'متى يفتح فرع جدة أبوابه يوم الجمعة؟', 'expect' => 'من الساعة الواحدة ظهراً حتى الحادية عشرة ليلاً'],
    ['query' => 'ما مواعيد فرع جدة في الليل خلال شهر رمضان؟', 'expect' => 'من التاسعة والنصف مساءً حتى الثالثة فجراً'],
    ['query' => 'هل فرع جدة مفتوح في أيام عيد الفطر؟', 'expect' => 'في عيد الفطر نغلق أول يومين'],
    ['query' => 'بكم أستأجر كاميرا تصوير تحت الماء ليوم كامل؟', 'expect' => 'كاميرا مائية | ٦٠ ريالاً | ٩٥ ريالاً'],
    ['query' => 'ماذا تحسبون إذا تأخرت في إرجاع معدات السنوركل؟', 'expect' => '١٠ ريالات عن كل ساعة'],
    ['query' => 'كم تكلف باقة المبيت على البحر لعائلة من أربعة أفراد؟', 'expect' => 'لأربعة أشخاص بسعر ١٦٠ ريالاً'],
    ['query' => 'أين أنصب خيمتي على الشاطئ حتى لا يصلها الماء في الليل؟', 'expect' => 'فوق خط المد العالي'],
    ['query' => 'لسعني قنديل بحر، ماذا أفعل؟', 'expect' => 'اشطف المكان بماء البحر'],
    ['query' => 'في أي يوم تقام دورة الإسعافات الأولية في جدة؟', 'expect' => 'كل يوم ثلاثاء من السادسة مساءً'],
    ['query' => 'كم رسوم شحن طلبي من جدة إلى المدينة المنورة؟', 'expect' => 'من يومين إلى ثلاثة أيام عمل | ٣٢ ريالاً'],
    ['query' => 'بدلة الغطس عندي فيها شق، كم يكلف إصلاحها؟', 'expect' => 'رقعة لشق في بدلة النيوبرين | ٤٥ ريالاً'],
    ['query' => 'هل توجد أوقات تسوق خاصة بالسيدات في فرع جدة؟', 'expect' => 'كل يوم ثلاثاء وخميس من التاسعة صباحاً'],
    // Asked in English, answered by the Arabic text.
    ['query' => 'How many loyalty points do I earn for each riyal I spend?', 'expect' => 'نقطة واحدة عن كل ١٠ ريالات'],
    ['query' => 'Can I return a snorkel mask after I have opened the package?', 'expect' => 'لا نقبل إرجاع الأقنعة وأنابيب التنفس'],
    ['query' => 'What should I do if a current pulls me out to sea while snorkelling?', 'expect' => 'اسبح موازياً للشاطئ'],
    ['query' => 'How much is the sunset boat tour in Jeddah?', 'expect' => 'جولة الغروب على الساحل | ساعتان | ١٢٠ ريالاً'],
    ['query' => 'How old do you have to be for the sea kayak course?', 'expect' => 'لمن عمره اثنا عشر عاماً فما فوق'],

    // Not covered: nothing should come back.
    ['query' => 'هل لديكم فرع في الدمام، وما مواعيده؟', 'expect' => null],
    ['query' => 'Do you offer PADI scuba diving certification in Jeddah?', 'expect' => null],
    ['query' => 'هل يمكنني استئجار دراجة مائية (جت سكي) من فرع جدة؟', 'expect' => null],

    // Multi-hop: the answer joins facts from two chunks.
    ['query' => 'أريد حجز رحلة الغطس للنساء فقط، متى تكون ومن أي رصيف تنطلق القوارب؟', 'kind' => 'multi-hop', 'expect' => ['رحلة غطس سطحي للنساء فقط في الجمعة الأولى', 'الرصيف رقم ٤']],
    ['query' => 'If I book the reef snorkelling boat trip as a loyalty member, do I get a discount, and do I earn points on it?', 'kind' => 'multi-hop', 'expect' => ['خصم ١٠٪ إذا حجزوا من مكتب التأجير', 'رحلات القوارب لا تُحسب عليها نقاط']],
    ['query' => 'كم يكلف تغيير سحاب باب الخيمة في ورشة الرياض، وكم يكلف تغيير سحاب خيمة الشاطئ في ورشة جدة؟', 'kind' => 'multi-hop', 'expect' => ['تغيير سحّاب باب الخيمة | ٧٠ ريالاً', 'تغيير سحّاب خيمة الشاطئ | ٦٥ ريالاً']],

    // Global: the facts are spread over the whole guide.
    ['query' => 'لخّص لي كل الخدمات التي يقدمها متجر جدة.', 'kind' => 'global', 'expect' => ['الطقم البحري الكامل', 'باقة ليلة على الشاطئ', 'قوارب اللؤلؤة الزرقاء', 'الإسعافات الأولية على الشاطئ', 'ورشة جدة متخصصة في معدات البحر', 'يرسل متجر جدة الطلبات بنفسه']],
];
