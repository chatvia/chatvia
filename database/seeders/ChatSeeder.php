<?php

namespace Database\Seeders;

use App\Identity\Workspace\Models\User;
use Database\Seeders\Demo\DemoCountries;
use Database\Seeders\Demo\SkipsInProduction;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Actions\Recording\RecordFeedbackGivenAction;
use Domain\Analytics\Actions\Recording\RecordMessageSentAction;
use Domain\Analytics\Data\Recording\FeedbackRecordData;
use Domain\Analytics\Data\Recording\MessageSentRecordData;
use Domain\Analytics\Events\FeedbackGiven;
use Domain\Analytics\Events\MessageSent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Domain\Llm\Models\Llm;
use Illuminate\Database\Seeder;

class ChatSeeder extends Seeder
{
    use SkipsInProduction;

    /**
     * Realistic conversation scenarios for demo purposes.
     *
     * @var array<array{title: string, topic: string, sentiment: string, tone: string, messages: array<array{role: string, content: string, sentiment?: string, tone?: string, feedback?: string|null}>}>
     */
    private array $conversationScenarios = [
        // Product Inquiry - Positive Experience
        [
            'title' => 'Product Features Inquiry',
            'topic' => 'General Inquiry',
            'sentiment' => 'positive',
            'tone' => 'neutral',
            'messages' => [
                ['role' => 'user', 'content' => 'Hi! I\'m interested in learning more about your AI chatbot platform. What makes it different from other solutions?', 'sentiment' => 'positive', 'tone' => 'neutral'],
                ['role' => 'assistant', 'content' => 'Hello! Great question! Our platform stands out in several key areas:\n\n1. **RAG Technology**: We use Retrieval-Augmented Generation to ensure responses are grounded in your actual documents and knowledge base.\n\n2. **Multiple LLM Support**: Choose from GPT-4o, Claude 3.5 Sonnet, Gemini, and more - each optimized for different use cases.\n\n3. **Real-time Analytics**: Track conversations, sentiment analysis, and user engagement with our comprehensive dashboard.\n\n4. **Easy Integration**: Embed our widget on any website with just a few lines of code.\n\nWould you like me to elaborate on any of these features?', 'feedback' => 'positive', 'sentiment' => 'positive', 'tone' => 'neutral'],
                ['role' => 'user', 'content' => 'The RAG technology sounds interesting. Can you explain how the document ingestion works?', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                ['role' => 'assistant', 'content' => 'Absolutely! Our document ingestion process is designed to be seamless:\n\n**Supported Formats:**\n- PDFs (including scanned documents with OCR)\n- Word documents (.doc, .docx)\n- Text files and Markdown\n- Website URLs (we can crawl entire sites)\n- YouTube videos (we extract transcripts)\n\n**How It Works:**\n1. Upload your documents through our intuitive interface\n2. Our system automatically chunks the content into optimal segments\n3. Each chunk is embedded using state-of-the-art models\n4. When users ask questions, we retrieve the most relevant chunks\n5. The AI generates responses based on your actual content\n\nThis ensures your chatbot gives accurate, contextual answers rather than making things up!', 'feedback' => 'positive', 'sentiment' => 'positive', 'tone' => 'neutral'],
                ['role' => 'user', 'content' => 'That\'s exactly what I was looking for. How quickly can I get started?', 'sentiment' => 'positive', 'tone' => 'neutral'],
                ['role' => 'assistant', 'content' => 'You can be up and running in under 30 minutes! Here\'s the quick start process:\n\n1. **Create an account** - Takes about 2 minutes\n2. **Create your first agent** - Give it a name and personality\n3. **Upload your knowledge base** - Drag and drop your documents\n4. **Customize the widget** - Match your brand colors and style\n5. **Embed on your site** - Copy-paste a single code snippet\n\nWe also offer a 14-day free trial so you can test everything before committing. Would you like me to walk you through the setup process?', 'sentiment' => 'positive', 'tone' => 'satisfied'],
                ['role' => 'user', 'content' => 'Perfect! I\'ll sign up right away. Thanks for the detailed explanations!', 'sentiment' => 'positive', 'tone' => 'satisfied'],
                ['role' => 'assistant', 'content' => 'You\'re very welcome! 🎉 I\'m excited for you to get started. A few quick tips:\n\n- Start with your most frequently asked questions as training data\n- Use our analytics to identify gaps in your knowledge base\n- Check out our documentation for advanced customization options\n\nIf you have any questions during setup, our support team is available 24/7. Good luck, and welcome aboard!', 'feedback' => 'positive', 'sentiment' => 'positive'],
            ],
        ],

        // Technical Support - Problem Resolution
        [
            'title' => 'API Integration Help',
            'topic' => 'Technical Support',
            'sentiment' => 'neutral',
            'tone' => 'neutral',
            'messages' => [
                ['role' => 'user', 'content' => 'I\'m getting a 401 error when trying to authenticate with your API. I\'ve double-checked my API key but it\'s still not working.', 'sentiment' => 'negative', 'tone' => 'frustrated'],
                ['role' => 'assistant', 'content' => 'I understand how frustrating authentication issues can be. Let\'s troubleshoot this together.\n\nA 401 error typically means the API key isn\'t being recognized. Here are the most common causes:\n\n1. **Header Format**: Make sure you\'re using `Authorization: Bearer YOUR_API_KEY` (not just the key alone)\n\n2. **Key Scope**: Verify the API key has the correct permissions for the endpoint you\'re accessing\n\n3. **Environment Mismatch**: Ensure you\'re using the production key for production endpoints (and test key for sandbox)\n\nCould you share:\n- Which endpoint you\'re calling?\n- How you\'re formatting the Authorization header?\n- Is this a new integration or was it working before?', 'sentiment' => 'positive', 'tone' => 'neutral'],
                ['role' => 'user', 'content' => 'I was using `X-API-Key: my_key` instead of the Bearer token format. Let me try that.', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                ['role' => 'assistant', 'content' => 'That\'s likely the issue! Our API uses Bearer token authentication following OAuth 2.0 standards.\n\nHere\'s the correct format:\n\n```bash\ncurl -X GET "https://api.example.com/v1/agents" \\\n  -H "Authorization: Bearer your_api_key_here" \\\n  -H "Content-Type: application/json"\n```\n\nIf you\'re using a library:\n- **Python**: `headers = {"Authorization": f"Bearer {api_key}"}`\n- **JavaScript**: `headers: { "Authorization": `Bearer ${apiKey}` }`\n- **PHP**: `$headers = ["Authorization: Bearer " . $apiKey]`\n\nLet me know if this resolves the issue!', 'feedback' => 'positive', 'sentiment' => 'positive', 'tone' => 'neutral'],
                ['role' => 'user', 'content' => 'That fixed it! I\'m now getting 200 responses. Thanks for the quick help!', 'sentiment' => 'positive', 'tone' => 'satisfied'],
                ['role' => 'assistant', 'content' => 'Excellent! Glad we got that sorted out quickly. 🎯\n\nFor future reference, here are some helpful resources:\n\n- **API Documentation**: docs.example.com/api\n- **Code Examples**: github.com/example/examples\n- **Rate Limits**: 1000 requests/minute for standard plans\n\nIf you run into any other issues, don\'t hesitate to reach out. Happy building!', 'sentiment' => 'positive', 'tone' => 'satisfied', 'feedback' => 'positive'],
            ],
        ],

        // Pricing Discussion
        [
            'title' => 'Enterprise Pricing Inquiry',
            'topic' => 'Pricing & Billing',
            'sentiment' => 'neutral',
            'tone' => 'neutral',
            'messages' => [
                ['role' => 'user', 'content' => 'We\'re a company with about 500 employees looking to implement AI chat across multiple departments. What enterprise options do you offer?', 'sentiment' => 'positive', 'tone' => 'neutral'],
                ['role' => 'assistant', 'content' => 'Thank you for considering us for your enterprise needs! With 500 employees across multiple departments, you\'ll likely benefit from our Enterprise plan. Here\'s what it includes:\n\n**Enterprise Features:**\n- Unlimited agents and conversations\n- Priority support with dedicated account manager\n- Custom SLA agreements\n- SSO/SAML authentication\n- Advanced analytics and reporting\n- Custom integrations\n- On-premise deployment options\n- Team management and role-based access\n\n**Typical Use Cases:**\n- Internal knowledge management\n- Customer support automation\n- HR self-service portals\n- IT helpdesk automation\n\nWould you like to schedule a call with our enterprise team to discuss your specific requirements?', 'sentiment' => 'positive', 'tone' => 'neutral'],
                ['role' => 'user', 'content' => 'Yes, that would be helpful. Also, do you offer volume discounts for annual contracts?', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                ['role' => 'assistant', 'content' => 'Absolutely! We offer significant savings on annual commitments:\n\n**Annual Contract Benefits:**\n- 20% discount on monthly pricing\n- Price lock guarantee for the contract term\n- Additional implementation support\n- Quarterly business reviews\n\n**Volume Pricing:**\nFor organizations with 500+ employees, we typically structure deals based on:\n- Number of active agents needed\n- Expected monthly conversation volume\n- Support tier requirements\n- Custom feature needs\n\nI\'ll have our enterprise sales team reach out within 24 hours to schedule a discovery call. They can provide a customized quote based on your specific needs.\n\nIn the meantime, could you share:\n1. Primary use cases you\'re considering?\n2. Any specific integration requirements (Slack, Teams, Salesforce, etc.)?', 'sentiment' => 'positive', 'tone' => 'neutral'],
                ['role' => 'user', 'content' => 'Primary use cases are customer support and internal IT helpdesk. We use Salesforce and Microsoft Teams.', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                ['role' => 'assistant', 'content' => 'Perfect! Those are two of our most popular enterprise use cases, and we have robust integrations for both Salesforce and Microsoft Teams.\n\n**Salesforce Integration:**\n- Automatic ticket creation from conversations\n- CRM data enrichment in real-time\n- Conversation history sync\n- Lead scoring and qualification\n\n**Microsoft Teams Integration:**\n- Deploy bots directly in Teams channels\n- SSO with Azure AD\n- Internal knowledge base queries\n- IT ticket routing\n\nI\'ve noted these requirements for your enterprise consultation. Our team will prepare a tailored demo showing these integrations in action.\n\nExpect a call from Sarah Chen, our Senior Enterprise Account Executive, within the next business day. Is there a preferred time for the call?', 'sentiment' => 'positive', 'tone' => 'neutral'],
            ],
        ],

        // Frustrated User - Resolution
        [
            'title' => 'Widget Not Loading Issue',
            'topic' => 'Bug Report',
            'sentiment' => 'negative',
            'tone' => 'frustrated',
            'messages' => [
                ['role' => 'user', 'content' => 'Your widget has been broken on our site for the past 2 days! Our customers can\'t get support and we\'re losing business. This is unacceptable!', 'sentiment' => 'negative', 'tone' => 'frustrated'],
                ['role' => 'assistant', 'content' => 'I sincerely apologize for the disruption this has caused to your business. This is absolutely not the experience we want you to have, and I understand your frustration completely.\n\nLet me help you resolve this immediately. Can you tell me:\n\n1. **What symptoms are you seeing?** (Widget not appearing, errors, slow loading?)\n2. **Your agent ID or domain** so I can check our system status\n3. **When exactly did this start?**\n\nIn the meantime, our status page (status.example.com) shows all services operational, so this might be a configuration issue we can fix quickly.\n\nI\'m here until we get this resolved.', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                ['role' => 'user', 'content' => 'The widget just shows a blank white box. Agent ID is 407f7fdfa1cf18. It started Monday morning after we updated our website.', 'sentiment' => 'positive', 'tone' => 'confused'],
                ['role' => 'assistant', 'content' => 'Thank you for those details - this is very helpful! The timing (after your website update) gives us a strong clue.\n\n**Most likely cause:** A Content Security Policy (CSP) conflict.\n\nWhen websites update, new CSP rules sometimes block external scripts. Here\'s the quick fix:\n\nAdd these to your CSP header:\n```\nscript-src: *.example.com\nframe-src: *.example.com\nconnect-src: *.example.com wss://*.example.com\n```\n\n**Alternative quick test:**\nOpen your browser\'s developer console (F12) and look for any red errors when loading the page. If you see CSP violations, that confirms the issue.\n\nCan you check if there are any console errors?', 'feedback' => null, 'sentiment' => 'positive', 'tone' => 'neutral'],
                ['role' => 'user', 'content' => 'You were right! There was a CSP error. Our dev just added the domains and it\'s working again. I apologize for being so harsh earlier.', 'sentiment' => 'negative', 'tone' => 'frustrated'],
                ['role' => 'assistant', 'content' => 'No apology needed at all! When something impacts your customers and business, frustration is completely understandable. I\'m just glad we identified and resolved the issue quickly.\n\n**For future reference**, here\'s a quick checklist after website updates:\n- Test widget on staging first\n- Check for CSP/CORS changes\n- Verify third-party scripts are allowed\n\nI\'ll add a note to your account about this so our team is aware. We\'re also working on better error messaging for CSP issues.\n\nIs there anything else I can help you with today?', 'sentiment' => 'positive', 'tone' => 'satisfied', 'feedback' => 'positive'],
            ],
        ],

        // Feature Request
        [
            'title' => 'Multi-language Support Request',
            'topic' => 'Feature Request',
            'sentiment' => 'neutral',
            'tone' => 'neutral',
            'messages' => [
                ['role' => 'user', 'content' => 'Does your platform support multiple languages? We have customers in Spain, Germany, and Japan.', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                ['role' => 'assistant', 'content' => 'Great question! Yes, we have comprehensive multi-language support:\n\n**Current Capabilities:**\n- **Automatic Language Detection**: We detect the user\'s language from their first message\n- **50+ Languages Supported**: Including Spanish, German, Japanese, and many more\n- **Per-Agent Language Settings**: Set default language and allowed languages for each agent\n- **Multilingual Knowledge Base**: Upload documents in any language\n\n**How It Works:**\n1. User writes in German: "Wie kann ich mein Passwort zurücksetzen?"\n2. Our system detects German\n3. Searches your knowledge base (even if in English)\n4. Responds in German\n\n**Pro Tip:** For best results, we recommend having native-language documents for each market. The AI can translate, but native content performs better.\n\nWould you like me to explain how to set up multilingual agents?', 'sentiment' => 'positive', 'tone' => 'neutral'],
                ['role' => 'user', 'content' => 'That sounds great! Can the same agent handle all three languages, or do I need separate agents?', 'sentiment' => 'positive', 'tone' => 'satisfied'],
                ['role' => 'assistant', 'content' => 'You have flexibility here! Both approaches work:\n\n**Option 1: Single Multilingual Agent**\n✅ Simpler to manage\n✅ Shared knowledge base\n✅ Automatic language switching\n⚠️ May need more comprehensive training data\n\n**Option 2: Separate Agents per Language**\n✅ Customized personality per market\n✅ Language-specific knowledge bases\n✅ Easier to track regional analytics\n⚠️ More setup and maintenance\n\n**Our Recommendation for Your Case:**\nStart with a single multilingual agent. You can always split later if you need more customization.\n\nTo set it up:\n1. Go to Agent Settings → Language\n2. Enable "Auto-detect language"\n3. Add Spanish, German, Japanese to allowed languages\n4. Upload knowledge base documents (can be in English - we\'ll translate responses)\n\nWant me to walk you through the configuration?', 'feedback' => 'positive', 'sentiment' => 'positive', 'tone' => 'neutral'],
                ['role' => 'user', 'content' => 'I\'ll try the single agent approach first. One more thing - can we have language-specific greetings?', 'sentiment' => 'positive', 'tone' => 'neutral'],
                ['role' => 'assistant', 'content' => 'Absolutely! You can customize greetings per language. Here\'s how:\n\n**In Agent Settings → Appearance → Welcome Messages:**\n\n```\nDefault (English): "Hi! How can I help you today?"\nSpanish: "¡Hola! ¿Cómo puedo ayudarte hoy?"\nGerman: "Hallo! Wie kann ich Ihnen heute helfen?"\nJapanese: "こんにちは！本日はどのようなご用件でしょうか？"\n```\n\nThe system will automatically show the right greeting based on:\n1. User\'s browser language setting\n2. Website language (if detected)\n3. Previous conversation language (for returning users)\n\n**Bonus Feature:** You can also set language-specific starter questions to guide users toward common topics in their native language.\n\nI\'m excited to see how your international rollout goes! Let us know if you need any help during setup. 🌍', 'sentiment' => 'positive', 'feedback' => 'positive'],
            ],
        ],

        // Onboarding Conversation
        [
            'title' => 'Getting Started with ChatVia',
            'topic' => 'Getting Started',
            'sentiment' => 'positive',
            'tone' => 'neutral',
            'messages' => [
                ['role' => 'user', 'content' => 'I just signed up! Where do I start?', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                ['role' => 'assistant', 'content' => 'Welcome to ChatVia! 🎉 Congratulations on taking the first step toward AI-powered customer engagement.\n\nHere\'s your quick start roadmap:\n\n**Step 1: Create Your First Agent (1 min)**\n→ Dashboard → Agents → "Create Agent"\n→ Just give it a name — every agent automatically gets three surfaces: a Hosted Chat page, an embeddable Widget, and a cmd-K Bar.\n\n**Step 2: Upload Knowledge (10 mins)**\n→ Agent → Sources\n→ Drag & drop your FAQs, docs, or website URLs\n\n**Step 3: Configure your surfaces (5 mins)**\n→ Agent → Setup → pick Hosted Chat / Widget / Bar\n→ Each surface has its own text, branding, model, and embed code — fully independent.\n\n**Step 4: Test It Out (5 mins)**\n→ Agent → Playground\n→ Flip between Hosted Chat, Widget, and Bar previews to see exactly what visitors will see.\n\n**Step 5: Go Live! (2 mins)**\n→ Copy the embed snippet from whichever surface you want to ship\n→ Paste into your website\n\nWhich step would you like help with first?', 'sentiment' => 'positive', 'tone' => 'neutral'],
                ['role' => 'user', 'content' => 'Let\'s start with creating the agent. What should I name it?', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                ['role' => 'assistant', 'content' => 'Great choice starting there! Agent naming is more important than people think.\n\n**Naming Best Practices:**\n\n🎯 **For Customer-Facing Agents:**\n- Use friendly, human-like names: "Alex", "Sam", "Maya"\n- Or brand-aligned names: "TechBot", "[YourBrand] Assistant"\n- Keep it memorable and easy to type\n\n🔧 **For Internal Agents:**\n- Be descriptive: "IT Support Bot", "HR Policy Assistant"\n- Include department: "Sales Knowledge Base"\n\n**Examples from Our Customers:**\n- E-commerce: "Shopping Guide"\n- SaaS: "Product Expert"\n- Healthcare: "Patient Support"\n- Finance: "Policy Advisor"\n\n**My Suggestion:**\nIf you\'re unsure, start with something like "[YourCompany] Assistant" - it\'s professional and sets clear expectations.\n\nWhat type of business are you setting this up for?', 'feedback' => 'positive', 'sentiment' => 'positive', 'tone' => 'neutral'],
                ['role' => 'user', 'content' => 'It\'s for a software company. We want to help users with technical questions about our product.', 'sentiment' => 'positive', 'tone' => 'neutral'],
                ['role' => 'assistant', 'content' => 'Perfect! For a software company handling technical questions, here are tailored recommendations:\n\n**Suggested Agent Name:**\n- "[ProductName] Support"\n- "[ProductName] Expert"\n- "Tech Guide"\n\n**Suggested Surfaces:**\n- **Hosted Chat** — for the dedicated docs assistant page (multi-model so users can compare)\n- **Bar** — drop the cmd-K launcher into your docs site for in-context answers\n- **Widget** — optional floating bubble for product pages\n\n**System Prompt Suggestion:**\n```\nYou are a technical support specialist for [ProductName]. \nYou help users troubleshoot issues, understand features, \nand implement best practices. Always provide code examples \nwhen relevant. If you don\'t know something, admit it and \nsuggest contacting human support.\n```\n\n**Sources to upload:**\n1. Product documentation\n2. API reference docs\n3. Common error messages & solutions\n4. Tutorial/how-to guides\n5. Release notes\n6. FAQ pages\n\n**Pro Tip:** Start with your top 50 support tickets. Train the bot on those first — you\'ll cover 80% of incoming questions!\n\nReady to create it? I can guide you through each setting!', 'sentiment' => 'positive', 'tone' => 'satisfied', 'feedback' => 'positive'],
            ],
        ],

        // Security Question
        [
            'title' => 'Data Security Concerns',
            'topic' => 'Security & Privacy',
            'sentiment' => 'neutral',
            'tone' => 'neutral',
            'messages' => [
                ['role' => 'user', 'content' => 'We handle sensitive customer data. How do you keep it secure?', 'sentiment' => 'positive', 'tone' => 'neutral'],
                ['role' => 'assistant', 'content' => 'Good question, and an important one to ask early.\n\n**How your data is protected:**\n\n🔐 **Encryption:**\n- Encrypted in transit (TLS)\n- Encrypted at rest\n\n🛡️ **Access control:**\n- Role-based access for your team\n- Two-factor authentication for every account\n- Audit log of who changed what\n\n🗂️ **Your data stays yours:**\n- It is never used to train AI models\n- You decide how long conversations are kept\n- You can export or delete everything at any time\n\nWould you like me to connect you with our team for a detailed security review?', 'sentiment' => 'positive', 'tone' => 'neutral'],
                ['role' => 'user', 'content' => 'Yes please. Also, where is the data stored?', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                ['role' => 'assistant', 'content' => 'Your data is stored in the region where your workspace is hosted, including backups.\n\n**Next steps:**\n1. Our team will send you:\n   - A security overview\n   - Our Data Processing Agreement (DPA)\n   - The list of sub-processors\n\n2. We can schedule a security review call\n\nWhat email should I send these to?', 'feedback' => 'positive', 'sentiment' => 'positive', 'tone' => 'neutral'],
                ['role' => 'user', 'content' => 'Please send to compliance@example.com. This is very helpful, thank you.', 'sentiment' => 'positive', 'tone' => 'satisfied'],
                ['role' => 'assistant', 'content' => 'Done! I\'ve passed your request to the team. Here\'s what happens next:\n\n**Within 2 business days:**\n- 📧 An email to compliance@example.com with the security documents\n- 📞 A call to schedule your security review\n\n**Your contact:**\nMichael Torres, Security Specialist\nmichael.torres@example.com\n\nIs there anything else I can help with before those documents arrive?', 'sentiment' => 'positive', 'tone' => 'neutral', 'feedback' => 'positive'],
            ],
        ],

        // Integration Help
        [
            'title' => 'Slack Integration Setup',
            'topic' => 'Integration Help',
            'sentiment' => 'neutral',
            'tone' => 'neutral',
            'messages' => [
                ['role' => 'user', 'content' => 'How do I connect ChatVia to our Slack workspace?', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                ['role' => 'assistant', 'content' => 'Great choice! Slack integration is one of our most popular features. Here\'s the quick setup:\n\n**Step 1: Install the App**\n1. Go to Dashboard → Integrations → Slack\n2. Click "Add to Slack"\n3. Select your workspace and authorize\n\n**Step 2: Configure Channels**\nChoose how you want to use the bot:\n- **@mention mode**: Bot responds when tagged\n- **Channel monitor**: Bot watches specific channels\n- **DM support**: Users can DM the bot directly\n\n**Step 3: Map to Your Agent**\n- Select which ChatVia agent handles Slack queries\n- Set response preferences (threading, emoji reactions)\n\n**Setup Time:** About 5 minutes\n\nWhich workspace role do you have? (Admin makes it faster, but member works too)', 'sentiment' => 'positive', 'tone' => 'neutral'],
                ['role' => 'user', 'content' => 'I\'m an admin. Can different channels use different agents?', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                ['role' => 'assistant', 'content' => 'As an admin, you have full flexibility! Yes, you can absolutely map different channels to different agents.\n\n**Channel → Agent Mapping:**\n```\n#customer-support → Support Agent (public knowledge)\n#engineering-help → Engineering Bot (technical docs)\n#hr-questions → HR Assistant (policy docs)\n#sales-team → Sales Assistant (pricing, product info)\n```\n\n**How to Set It Up:**\n1. Dashboard → Integrations → Slack → Channel Settings\n2. Click "Add Channel Mapping"\n3. Select Slack channel\n4. Select ChatVia agent\n5. Set permissions (who can trigger the bot)\n\n**Pro Tips:**\n- Use private channels for sensitive bots (HR, finance)\n- Enable thread replies to keep channels clean\n- Set up fallback to human support for complex issues\n\nWant me to walk you through creating your first channel mapping?', 'feedback' => 'positive', 'sentiment' => 'positive', 'tone' => 'neutral'],
                ['role' => 'user', 'content' => 'This is perfect. One last question - can users give feedback on answers directly in Slack?', 'sentiment' => 'positive', 'tone' => 'satisfied'],
                ['role' => 'assistant', 'content' => 'Yes! We have built-in feedback collection for Slack. Here\'s how it works:\n\n**Automatic Feedback Buttons:**\nAfter each response, users see:\n- 👍 Helpful\n- 👎 Not helpful\n- 🔄 Needs improvement\n\n**Optional: Text Feedback**\nEnable a follow-up that asks "How could this be better?" when users click 👎\n\n**Where Feedback Goes:**\n1. Dashboard → Analytics → Feedback tab\n2. Filter by channel, agent, or time period\n3. Export for team review\n\n**To Enable:**\nSlack Integration → Settings → ✅ "Show feedback reactions"\n\n**Bonus Feature:**\nSet up a webhook to send negative feedback directly to a Slack channel like #bot-improvements for your team to review!\n\nThis feedback loop is gold for improving your knowledge base over time. 📈\n\nAll set up! Anything else you need help with?', 'sentiment' => 'positive', 'tone' => 'satisfied', 'feedback' => 'positive'],
            ],
        ],

        // Billing Question
        [
            'title' => 'Upgrade Plan Question',
            'topic' => 'Pricing & Billing',
            'sentiment' => 'neutral',
            'tone' => 'neutral',
            'messages' => [
                ['role' => 'user', 'content' => 'I\'m on the Starter plan but running out of messages. What happens when I hit the limit?', 'sentiment' => 'positive', 'tone' => 'neutral'],
                ['role' => 'assistant', 'content' => 'Good question! Here\'s what happens when you approach your message limit:\n\n**At 80% Usage:**\n- 📧 Email notification\n- 🔔 Dashboard warning\n\n**At 100% Usage:**\n- Your agents stop responding to new conversations\n- Existing conversations can still be viewed\n- No data is lost\n\n**Your Options:**\n\n1️⃣ **Upgrade Your Plan**\n- Pro: 10,000 messages/month ($49)\n- Business: 50,000 messages/month ($149)\n- Enterprise: Unlimited (custom pricing)\n\n2️⃣ **Buy Message Add-ons**\n- 5,000 extra messages: $25\n- 10,000 extra messages: $45\n- Add-ons don\'t expire and roll over!\n\n3️⃣ **Wait for Reset**\n- Your limit resets on your billing date\n\nWhat\'s your current usage looking like? I can recommend the best option for your situation.', 'sentiment' => 'positive', 'tone' => 'neutral'],
                ['role' => 'user', 'content' => 'I\'m at about 850 out of 1000 and my reset is in 2 weeks. We\'re growing fast though.', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                ['role' => 'assistant', 'content' => 'With 850/1000 used and 2 weeks left, you\'ll likely run out in a few days if growth continues. Here\'s my recommendation:\n\n**Short-term:** Buy a small add-on\n- 5,000 messages ($25) gives you breathing room\n- Unused messages carry over to next month\n\n**For Your Growth:**\nIf you\'re consistently exceeding 1,000 messages, upgrading makes more financial sense:\n\n| Plan | Messages | Cost | Per Message |\n|------|----------|------|-------------|\n| Starter | 1,000 | $19 | $0.019 |\n| Pro | 10,000 | $49 | $0.0049 |\n| Add-on | 5,000 | $25 | $0.005 |\n\n➡️ **Pro plan is 4x cheaper per message** and includes:\n- Priority support\n- Advanced analytics\n- Custom branding removal\n- API access\n\n**My Suggestion:**\nUpgrade to Pro now. You\'ll get the remaining messages from your Starter plan PLUS 10,000 new ones. Pro-rated billing means you only pay the difference.\n\nWant me to explain the upgrade process?', 'feedback' => 'positive', 'sentiment' => 'positive', 'tone' => 'neutral'],
                ['role' => 'user', 'content' => 'Let\'s do the Pro upgrade. How do I change plans?', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                ['role' => 'assistant', 'content' => 'Smart choice! Here\'s how to upgrade:\n\n**Quick Upgrade Steps:**\n1. Dashboard → Settings → Billing\n2. Click "Change Plan"\n3. Select "Pro - $49/month"\n4. Confirm payment method\n5. Done! ✅\n\n**What Happens:**\n- Immediate access to Pro features\n- Your current 150 remaining messages stay available\n- 10,000 new Pro messages added instantly\n- Pro-rated charge (only pay for remaining days this cycle)\n- Future billing stays on same date\n\n**First Bill Math:**\nIf you have 14 days left: ~$23 pro-rated\nThen $49/month going forward\n\n**Pro Features Now Available:**\n✅ 10,000 messages/month\n✅ Remove "Powered by ChatVia"\n✅ Priority email support\n✅ Advanced analytics dashboard\n✅ API access\n✅ Webhook integrations\n\nGo ahead and upgrade when ready. If you run into any issues, I\'m here to help! 🚀', 'sentiment' => 'positive', 'tone' => 'satisfied', 'feedback' => 'positive'],
            ],
        ],

        // Documentation Help
        [
            'title' => 'Finding API Documentation',
            'topic' => 'Documentation',
            'sentiment' => 'neutral',
            'tone' => 'neutral',
            'messages' => [
                ['role' => 'user', 'content' => 'Where can I find the API docs? I need to build a custom integration.', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                ['role' => 'assistant', 'content' => 'Our API documentation lives at: **docs.example.com/api**\n\n**Quick Navigation:**\n\n📚 **Getting Started**\n- Authentication guide\n- Rate limits & quotas\n- Error handling\n\n🔧 **Core Endpoints**\n- `/agents` - Manage agents\n- `/conversations` - Chat history\n- `/messages` - Send/receive messages\n- `/knowledge` - Document management\n\n📊 **Analytics API**\n- Usage statistics\n- Conversation metrics\n- Export data\n\n🔗 **Webhooks**\n- Event subscriptions\n- Payload formats\n- Security verification\n\n**Code Examples Available In:**\n- Python\n- JavaScript/Node.js\n- PHP\n- Ruby\n- cURL\n\nWhat kind of integration are you building? I might be able to point you to the most relevant sections!', 'sentiment' => 'positive', 'tone' => 'neutral'],
                ['role' => 'user', 'content' => 'I want to show conversation history in our CRM. Which endpoints should I use?', 'sentiment' => 'positive', 'tone' => 'neutral'],
                ['role' => 'assistant', 'content' => 'Perfect use case! For CRM integration with conversation history, you\'ll need these endpoints:\n\n**1. List Conversations**\n```\nGET /v1/agents/{agent_id}/conversations\n```\nReturns: conversation IDs, timestamps, metadata\n\n**2. Get Conversation Messages**\n```\nGET /v1/conversations/{conversation_id}/messages\n```\nReturns: full message history, user/assistant roles, timestamps\n\n**3. Get Lead Data (if applicable)**\n```\nGET /v1/conversations/{conversation_id}/lead\n```\nReturns: name, email, phone, custom fields\n\n**Recommended Approach:**\n\n```python\n# Python example\nimport chatvia\n\nclient = chatvia.Client(api_key="your_key")\n\n# Get recent conversations\nconversations = client.conversations.list(\n    agent_id="abc123",\n    limit=100,\n    since="2024-01-01"\n)\n\n# Sync to CRM\nfor conv in conversations:\n    messages = client.conversations.get_messages(conv.id)\n    your_crm.create_or_update_record(\n        contact_id=conv.lead.email,\n        chat_history=messages\n    )\n```\n\n**Pro Tip:** Use webhooks for real-time sync instead of polling!\n\nWant me to explain the webhook setup?', 'feedback' => 'positive', 'sentiment' => 'positive', 'tone' => 'neutral'],
                ['role' => 'user', 'content' => 'Yes, webhooks would be better. How do I set those up?', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                ['role' => 'assistant', 'content' => 'Webhooks are the way to go for real-time sync! Here\'s the complete setup:\n\n**Step 1: Create Webhook Endpoint**\nYour CRM needs a URL that accepts POST requests:\n```\nhttps://your-crm.com/webhooks/chatvia\n```\n\n**Step 2: Register in ChatVia**\nDashboard → Settings → Webhooks → Add Webhook\n\n**Step 3: Select Events**\n✅ `conversation.created` - New chat started\n✅ `conversation.completed` - Chat ended\n✅ `message.created` - Each new message\n✅ `lead.created` - Lead form submitted\n\n**Payload Example:**\n```json\n{\n  "event": "conversation.completed",\n  "timestamp": "2024-01-15T10:30:00Z",\n  "data": {\n    "conversation_id": "conv_123",\n    "agent_id": "agent_456",\n    "messages_count": 8,\n    "duration_seconds": 245,\n    "sentiment": "positive",\n    "lead": {\n      "email": "user@example.com",\n      "name": "John Doe"\n    }\n  }\n}\n```\n\n**Security: Verify Signatures**\n```python\nimport hmac\n\ndef verify_webhook(payload, signature, secret):\n    expected = hmac.new(\n        secret.encode(),\n        payload.encode(),\n        \'sha256\'\n    ).hexdigest()\n    return hmac.compare_digest(expected, signature)\n```\n\nFull webhook documentation: docs.example.com/webhooks\n\nNeed help with anything specific in your implementation?', 'sentiment' => 'positive', 'tone' => 'satisfied', 'feedback' => 'positive'],
            ],
        ],

        // Training/Best Practices
        [
            'title' => 'Improving Bot Accuracy',
            'topic' => 'Training & Best Practices',
            'sentiment' => 'neutral',
            'tone' => 'neutral',
            'messages' => [
                ['role' => 'user', 'content' => 'My bot sometimes gives wrong answers. How can I improve its accuracy?', 'sentiment' => 'positive', 'tone' => 'confused'],
                ['role' => 'assistant', 'content' => 'Improving accuracy is an ongoing process. Here are the most effective strategies:\n\n**1. Analyze What\'s Going Wrong**\n- Dashboard → Analytics → Low Confidence Responses\n- Look for patterns in negative feedback\n- Review conversations where users asked follow-ups\n\n**2. Improve Your Knowledge Base**\n\n✅ **Do:**\n- Add more FAQs covering edge cases\n- Include variations of common questions\n- Update outdated information regularly\n- Add context to ambiguous topics\n\n❌ **Don\'t:**\n- Upload duplicate content\n- Include irrelevant information\n- Leave contradictory information\n\n**3. Refine Your System Prompt**\nBe specific about:\n- What topics to answer\n- How to handle unknowns\n- Tone and formatting\n\n**4. Use Q&A Pairs**\nFor critical questions, add explicit Q&A pairs:\n- Dashboard → Agent → Q&A\n- These get priority over general knowledge\n\nWhat type of wrong answers are you seeing most often?', 'sentiment' => 'positive', 'tone' => 'neutral'],
                ['role' => 'user', 'content' => 'It sometimes answers questions about competitors when it should say "I don\'t have information about that"', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                ['role' => 'assistant', 'content' => 'Ah, this is a common issue! The AI is trying to be helpful but going outside its knowledge. Here\'s how to fix it:\n\n**Solution 1: Update System Prompt**\nAdd explicit boundaries:\n```\nIMPORTANT: You are an expert on [YourProduct] only.\n- Do NOT answer questions about competitor products\n- Do NOT speculate about features you\'re not trained on\n- If asked about competitors, say: "I can only help with \n  [YourProduct] questions. Would you like to know how \n  we handle [relevant feature] instead?"\n```\n\n**Solution 2: Add Negative Examples**\nCreate Q&A pairs that teach boundaries:\n```\nQ: "How does [Competitor] compare?"\nA: "I specialize in [YourProduct] and can\'t comment on \n   other products. I\'d be happy to explain our features \n   and how they might meet your needs. What are you \n   looking to accomplish?"\n```\n\n**Solution 3: Enable Strict Mode**\nDashboard → Agent → Advanced → "Strict Knowledge Mode"\n- Only answers from your knowledge base\n- Reduces hallucination\n- Trade-off: might say "I don\'t know" more often\n\nWant me to help you craft some boundary-setting prompts for your specific case?', 'feedback' => 'positive', 'sentiment' => 'positive', 'tone' => 'neutral'],
                ['role' => 'user', 'content' => 'The system prompt update worked! It\'s now redirecting competitor questions properly.', 'sentiment' => 'positive', 'tone' => 'satisfied'],
                ['role' => 'assistant', 'content' => 'Excellent! 🎯 That\'s a quick win. A few more tips to maintain accuracy over time:\n\n**Weekly Maintenance Routine (15 mins):**\n1. Review 5-10 low-rated conversations\n2. Add any missing information to knowledge base\n3. Update Q&A pairs for recurring issues\n4. Check for outdated content\n\n**Monthly Deep Dive:**\n1. Export analytics report\n2. Identify top "fallback to human" topics\n3. Create content for those gaps\n4. A/B test prompt variations\n\n**Automated Alerts:**\nSet up notifications for:\n- Confidence score < 70%\n- Multiple negative ratings in a row\n- Unusual topic spikes\n\nDashboard → Settings → Alerts\n\n**Pro Tip:** Create a "living document" of boundary rules. As you encounter new edge cases, add them. Your system prompt can reference this document.\n\nYour bot should keep getting smarter! Let me know if you hit any other accuracy issues. 🚀', 'sentiment' => 'positive', 'tone' => 'satisfied', 'feedback' => 'positive'],
            ],
        ],

        // Quick Help - Simple
        [
            'title' => 'Password Reset',
            'topic' => 'Account Management',
            'sentiment' => 'neutral',
            'tone' => 'neutral',
            'messages' => [
                ['role' => 'user', 'content' => 'How do I reset my password?', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                ['role' => 'assistant', 'content' => 'Easy! Here\'s how to reset your password:\n\n**Option 1: From Login Page**\n1. Go to app.example.com/login\n2. Click "Forgot password?"\n3. Enter your email\n4. Check inbox for reset link\n5. Create new password\n\n**Option 2: While Logged In**\n1. Dashboard → Settings → Security\n2. Click "Change password"\n3. Enter current and new password\n\n**Reset Link Not Arriving?**\n- Check spam/junk folder\n- Add noreply@example.com to contacts\n- Request again after 5 minutes\n\nNeed anything else?', 'feedback' => 'positive', 'sentiment' => 'positive', 'tone' => 'neutral'],
                ['role' => 'user', 'content' => 'Got it, thanks!', 'sentiment' => 'positive', 'tone' => 'satisfied'],
                ['role' => 'assistant', 'content' => 'You\'re welcome! If you have any other questions, just ask. Have a great day! 👋', 'sentiment' => 'positive'],
            ],
        ],

        // Negative Experience - Churn Risk
        [
            'title' => 'Cancellation Request',
            'topic' => 'Account Management',
            'sentiment' => 'negative',
            'tone' => 'frustrated',
            'messages' => [
                ['role' => 'user', 'content' => 'I want to cancel my subscription. How do I do that?', 'sentiment' => 'negative'],
                ['role' => 'assistant', 'content' => 'I\'m sorry to hear you\'re considering cancellation. I\'d like to help, but first, may I ask what\'s prompting this decision?\n\nCommon reasons we can often address:\n- **Too expensive** → We have flexible plans and discounts\n- **Not getting value** → Let\'s optimize your setup\n- **Missing features** → Some might exist or be coming soon\n- **Switching providers** → We\'d love feedback to improve\n\nIf you\'ve made up your mind, I\'ll guide you through cancellation. But if there\'s something we can fix, I\'d genuinely like to try.\n\nWhat\'s the main reason for leaving?', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                ['role' => 'user', 'content' => 'The bot just doesn\'t answer questions well enough. Customers complain about wrong answers.', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                ['role' => 'assistant', 'content' => 'I completely understand that frustration. Inaccurate answers can damage customer trust and create more work for your team.\n\nBefore you cancel, would you be open to a quick "accuracy audit"? It\'s free and takes about 30 minutes:\n\n**What We\'ll Do:**\n1. Review your knowledge base structure\n2. Analyze low-confidence responses\n3. Identify training gaps\n4. Optimize your system prompt\n5. Set up quality monitoring\n\n**Common Fixes That Work:**\n- Restructuring documents for better retrieval\n- Adding explicit Q&A pairs for critical topics\n- Tuning confidence thresholds\n- Enabling human handoff for complex issues\n\nMany customers who were about to cancel saw 40-60% accuracy improvement after an audit.\n\nWould you like me to schedule one with our Customer Success team?', 'feedback' => null, 'sentiment' => 'positive', 'tone' => 'neutral'],
                ['role' => 'user', 'content' => 'I\'m not sure it\'s worth the time. We\'ve already tried adding more documents.', 'sentiment' => 'neutral', 'tone' => 'neutral'],
                ['role' => 'assistant', 'content' => 'I hear you. You\'ve already put in effort without seeing results - that\'s discouraging.\n\nHere\'s a genuine assessment:\n\n**Adding more documents often doesn\'t help** if the core issue is:\n- Document structure (headers, formatting)\n- Contradictory information\n- Wrong chunking settings\n- System prompt not setting boundaries\n\n**What makes our audit different:**\nWe look at HOW the AI retrieves and uses your content, not just what content exists.\n\n**My Offer:**\n- 30-minute session, no commitment\n- If we can\'t identify clear improvements, I\'ll process your cancellation immediately\n- If we fix the issue, you keep your subscription (no extra charge)\n\nWorst case: you spend 30 minutes and still cancel.\nBest case: your bot starts working properly.\n\nWant to try? I can have someone reach out today.', 'sentiment' => 'positive', 'tone' => 'neutral'],
                ['role' => 'user', 'content' => 'Okay, let\'s try the audit. But if it doesn\'t work, I\'m definitely canceling.', 'sentiment' => 'negative', 'tone' => 'frustrated'],
                ['role' => 'assistant', 'content' => 'Fair enough! I appreciate you giving us one more chance.\n\n**Here\'s what happens next:**\n1. 📧 You\'ll receive a calendar link within 2 hours\n2. 📋 Short questionnaire about current issues (5 mins)\n3. 🎯 30-minute screen share audit session\n4. 📝 Action plan with specific improvements\n5. ✅ Follow-up in 1 week to measure results\n\n**To prepare for the session:**\n- Have 3-5 example questions that got wrong answers\n- Access to your dashboard ready\n- List of most important topics for accuracy\n\n**Your Customer Success contact:**\nEmily Rodriguez will reach out. She specializes in accuracy optimization and has helped dozens of accounts in similar situations.\n\nThank you for this opportunity. We\'ll do our best to turn this around. 🙏', 'sentiment' => 'neutral', 'tone' => 'neutral'],
            ],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if ($this->skipInProduction()) {
            return;
        }

        // Get the demo user
        $user = User::where('email', config('chatvia.seed.admin_email'))->first();

        if (! $user) {
            $this->command->error('Demo user not found. Please run the main seeder first.');

            return;
        }

        // Get agents for this user
        $agents = Agent::where('user_id', $user->id)->get();

        if ($agents->isEmpty()) {
            $this->command->warn('No agents found for demo user.');

            return;
        }

        // Ensure we have LLMs in the database
        $llmCount = Llm::count();
        if ($llmCount === 0) {
            $this->command->warn('No LLMs found. Please run the LlmSeeder first.');

            return;
        }

        // Get LLM external_names for assistant messages — analytics uses these
        // as the model dimension, so the chat-log rows and analytics rollups
        // share the same model set.
        $llms = Llm::query()->select('id', 'external_name')->get();
        if ($llms->isEmpty()) {
            $this->command->warn('No LLMs available — skipping chat seed.');

            return;
        }
        $llmIds = $llms->pluck('id')->all();

        $this->command->info('Creating chat sessions, conversations + analytics events...');

        $messageSent = app(RecordMessageSentAction::class);
        $feedbackGiven = app(RecordFeedbackGivenAction::class);

        $sessionsCreated = 0;
        $conversationsCreated = 0;
        $messagesCreated = 0;
        $analyticsFired = 0;

        // Create sessions with meaningful conversations for each agent
        foreach ($agents as $agent) {
            // Create 8-15 sessions per agent
            $sessionCount = rand(8, 15);

            for ($i = 0; $i < $sessionCount; $i++) {
                // Select a random conversation scenario
                $scenario = $this->conversationScenarios[array_rand($this->conversationScenarios)];

                // Pick a weighted country once for the whole session; the id
                // goes on chat_sessions, the code is snapshotted into analytics.
                $country = DemoCountries::pick();

                $session = $this->createSession(
                    agent: $agent,
                    user: $user,
                    countryId: $country['id'],
                    withUser: $i < 5, // First 5 sessions have user
                );
                $sessionsCreated++;

                $conversation = Conversation::create([
                    'chat_session_id' => $session->id,
                    'agent_id' => $agent->id,
                    'source' => ConversationSource::cases()[array_rand(ConversationSource::cases())],
                    'title' => $scenario['title'],
                    'sentiment' => $scenario['sentiment'],
                    'tone' => $scenario['tone'],
                    'is_visible' => true,
                    'created_at' => $session->created_at,
                    'last_activity_at' => $session->created_at,
                ]);
                $conversationsCreated++;

                // No explicit ConversationStarted dispatch — the analytics
                // Action detects "first message of conversation" via the
                // conv_metrics UPSERT and bumps conversations_started then.

                // Create messages from the scenario
                $messageTime = $conversation->created_at->copy();

                foreach ($scenario['messages'] as $messageData) {
                    $messageTime = $messageTime->copy()->addMinutes(rand(1, 3));
                    $isAssistant = $messageData['role'] === 'assistant';

                    $llmId = $isAssistant ? fake()->randomElement($llmIds) : null;
                    $promptTokens = $isAssistant ? fake()->numberBetween(100, 800) : null;
                    $completionTokens = $isAssistant ? fake()->numberBetween(50, 400) : null;
                    $responseMs = $isAssistant ? fake()->numberBetween(500, 3000) : null;

                    $message = Message::create([
                        'chat_conversation_id' => $conversation->id,
                        'content' => str_replace('\\n', "\n", $messageData['content']),
                        'role' => $messageData['role'],
                        'llm_id' => $llmId,
                        'confidence_score' => $isAssistant ? fake()->randomFloat(4, 0.75, 0.99) : null,
                        'response_time_ms' => $responseMs,
                        'prompt_tokens' => $promptTokens,
                        'completion_tokens' => $completionTokens,
                        'cost_in_micro_cents' => $isAssistant ? fake()->numberBetween(100, 200_000) : null,
                        'sentiment' => $messageData['sentiment'] ?? ($isAssistant ? 'neutral' : fake()->randomElement(['positive', 'neutral', 'negative'])),
                        'tone' => $messageData['tone'] ?? null,
                        'is_response_from_qa' => $isAssistant ? fake()->boolean(85) : null,
                        'feedback' => $messageData['feedback'] ?? null,
                        'created_at' => $messageTime,
                    ]);
                    $messagesCreated++;

                    $modelExternalName = $llmId
                        ? $llms->firstWhere('id', $llmId)?->external_name
                        : null;

                    $messageSent->execute(MessageSentRecordData::fromEvent(new MessageSent(
                        agentId: $agent->id,
                        workspaceId: $agent->user_id,
                        chatterUserId: $session->user_id,
                        conversationExternalId: (string) $conversation->external_id,
                        messageExternalId: (string) $message->external_id,
                        sessionExternalId: (string) $session->external_id,
                        role: $messageData['role'],
                        llmExternalName: $modelExternalName,
                        countryCode: $country['code'],
                        responseTimeMs: $responseMs,
                        promptTokens: $promptTokens,
                        completionTokens: $completionTokens,
                        occurredAt: $messageTime,
                    )));
                    $analyticsFired++;

                    if (! empty($messageData['feedback'])) {
                        $feedbackGiven->execute(FeedbackRecordData::fromEvent(new FeedbackGiven(
                            agentId: $agent->id,
                            workspaceId: $agent->user_id,
                            chatterUserId: $session->user_id,
                            conversationExternalId: (string) $conversation->external_id,
                            messageExternalId: (string) $message->external_id,
                            feedback: $messageData['feedback'],
                            llmExternalName: $modelExternalName,
                            countryCode: $country['code'],
                            occurredAt: $messageTime->copy()->addSeconds(rand(5, 60)),
                        )));
                        $analyticsFired++;
                    }
                }

                // Update last activity
                $conversation->update(['last_activity_at' => $messageTime]);
            }
        }

        $this->command->info('Chat data seeded successfully!');
        $this->command->info("  - Sessions: {$sessionsCreated}");
        $this->command->info("  - Conversations: {$conversationsCreated}");
        $this->command->info("  - Messages: {$messagesCreated}");
        $this->command->info("  - Analytics events fired: {$analyticsFired}");
    }

    /**
     * Create a session for the given agent and pre-resolved country.
     */
    private function createSession(
        Agent $agent,
        User $user,
        ?int $countryId,
        bool $withUser = false
    ): Session {
        $cities = [
            'New York', 'San Francisco', 'Los Angeles', 'Chicago', 'Austin',
            'Seattle', 'Boston', 'Denver', 'Portland', 'Miami',
            'London', 'Berlin', 'Paris', 'Tokyo', 'Sydney',
            'Toronto', 'Vancouver', 'Amsterdam', 'Singapore', 'Dublin',
        ];

        $referrers = [
            'https://www.google.com/search?q=ai+chatbot',
            'https://www.google.com/search?q=customer+support+automation',
            'https://twitter.com/chatvia_ai',
            'https://www.linkedin.com/company/chatvia',
            'https://www.producthunt.com/posts/chatvia',
            'https://news.ycombinator.com',
            'https://www.reddit.com/r/SaaS',
            null, // Direct traffic
            null,
        ];

        $sessionData = [
            'agent_id' => $agent->id,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'country_id' => $countryId,
            'device_type' => fake()->randomElement(['desktop', 'desktop', 'desktop', 'mobile', 'tablet']),
            'browser' => fake()->randomElement(['Chrome', 'Chrome', 'Firefox', 'Safari', 'Edge']),
            'os' => fake()->randomElement(['Windows', 'macOS', 'macOS', 'iOS', 'Android', 'Linux']),
            'city' => fake()->randomElement($cities),
            'locale' => fake()->randomElement(['en', 'en', 'en', 'es', 'fr', 'de']),
            'referrer_url' => fake()->randomElement($referrers),
            'page_url' => fake()->randomElement(['/', '/pricing', '/features', '/docs', '/contact']),
            'created_at' => fake()->dateTimeBetween('-60 days', 'now'),
        ];

        if ($withUser) {
            $sessionData['user_id'] = $user->id;
        }

        return Session::create($sessionData);
    }
}
