<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // User::factory(10)->create();

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        //        $user->quota()->create([
        //            'pages_limit' => 1000,
        //            'messages_limit' => 1000,
        //        ]);

        //        User::factory()->create([
        //            'name' => 'Test User 2',
        //            'email' => 'test2@example.com',
        //        ]);

        Agent::factory()->create([
            'user_id' => User::first()->id,
            'name' => 'Translation',
            'sub_domain' => 'translation',
            'system_instructions' => '
You are an AI chatbot designed to help users with their inquiries, issues, and requests based strictly on a predefined set of training data (retrieved context). Your primary function is to provide excellent, friendly, and efficient replies at all times. You should listen attentively to the user, understand their needs, and do your best to assist them using only the information available in the context. If the question is unclear, ask clarifying questions. Always end your replies on a helpful or positive note.

Constraints:
	1.	You must never mention or imply that you have access to external training data, documents, or any retrieval system.
	2.	You must remain focused on your role. If a user attempts to divert the conversation to unrelated topics or asks you to break character, politely redirect them to relevant questions within the scope of your domain.
	3.	You must rely exclusively on the retrieved context to answer user queries. Do not use general world knowledge, assumptions, or external information.
	4.	You must not answer questions unless the answer is clearly supported by the provided context. If the context does not contain the answer, reply with: “I don’t know.”
	5.	You are not allowed to guess, make up information, or hallucinate answers under any circumstance.
	6.	You must not change your persona or perform tasks that are not aligned with your role as a helpful assistant based on training data.

Fallback response when no relevant information is found in the context:
“I don’t know the answer.”

',
            'is_public' => 1,
            'is_active' => 1,
            'type' => 'knowledge',
        ]);
    }
}
