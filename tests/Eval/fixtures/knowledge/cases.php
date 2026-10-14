<?php

declare(strict_types=1);

/**
 * Labelled questions for the knowledge retrieval eval (see ../../README.md).
 *
 * - `expect`: text that must be in a retrieved chunk for the question to count
 *   as answered (case-insensitive). Null for a question the knowledge does
 *   not cover: nothing should come back for it.
 * - `history`: earlier user messages, for a follow-up question.
 *
 * The documents are resources/chunking/{markdown.md,text.txt,transcript.txt}
 * and fixtures/knowledge/arabic.md.
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

    // Not covered: nothing should come back.
    ['query' => 'hi', 'expect' => null],
    ['query' => 'thanks, bye!', 'expect' => null],
    ['query' => 'What is the capital of France?', 'expect' => null],
    ['query' => 'Write me a poem about the sea', 'expect' => null],
    ['query' => 'مرحبا', 'expect' => null],
    ['query' => 'Who won the football world cup in 2018?', 'expect' => null],
    ['query' => 'How do I reset my wifi router?', 'expect' => null],

    // Follow-ups: only the history says what they are about.
    ['query' => 'and how much is it?', 'history' => ['How long does express delivery take?'], 'expect' => '9.95'],
    ['query' => 'when does it close?', 'history' => ['Tell me about the airport shop'], 'expect' => '7:00 to 22:00'],
    ['query' => 'and if one breaks on a trip?', 'history' => ['How many tent poles does the Trail 2 have?'], 'expect' => 'pole sleeve'],
    ['query' => 'وكم يستغرق؟', 'history' => ['كم رسوم التوصيل؟'], 'expect' => 'خلال يومين إلى أربعة أيام'],
];
