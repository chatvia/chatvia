<?php

// Public marketing homepage (resources/views/landing/*). English is the source of
// truth and the default locale, so this file mirrors the copy shown on the site —
// keep it in sync with lang/ar/landing.php (same keys).

return [

    'meta' => [
        'title' => 'Chatvia.ai - Bygg & träna AI-agenter på din data | EU-hostad',
        'description' => 'Chatvia är B2B-plattformen för att bygga, träna och driftsätta AI-agenter på din egen data. Anslut MCP-servrar, anropa dina API:er med anpassade funktioner och låt agenter agera i dina verktyg. EU-hostad och GDPR-kompatibel.',
        'og_title' => 'Chatvia.ai - Bygg & träna dina egna AI-agenter',
        'og_description' => 'Ge ditt team AI-agenter som känner till din data och agerar i dina verktyg — MCP-servrar, anpassade funktioner och tool calling. Från idé till live-agent på minuter. EU-hostad och GDPR-kompatibel.',
    ],

    'nav' => [
        'home' => 'Hem',
        'features' => 'Funktioner',
        'pricing' => 'Priser',
        'mission' => 'Mission',
        'security' => 'Säkerhet',
        'dpa' => 'DPA',
        'dashboard' => 'Instrumentpanel',
        'logout' => 'Logga ut',
        'login' => 'Logga in',
        'get_started' => 'Kom igång',
        'menu' => 'Meny',
        'language' => 'Språk',
    ],

    'hero' => [
        'badge' => 'AI-agenter som känner ditt företag utan och innan',
        'title_line1' => 'Att bygga AI-agenter är svårt.',
        'title_make' => 'Vi gör det till en fråga om',
        'title_minutes' => 'minuter',
        'subtitle' => 'Skapa AI-agenter som bokar möten, kontrollerar lager och triggar arbetsflöden — med full förståelse för din verksamhet.',
        'no_code' => 'Ingen kod, inga utvecklare.',
        'eu_hosting' => 'Helt hostad i EU. Noll databehandling i USA.',
        'gdpr_alt' => 'GDPR-kompatibel',
        'cta' => 'Starta 7-dagars gratis provperiod',
    ],

    'integrations' => [
        'eyebrow' => 'Förstärk din agent',
        'title' => 'Direkta integrationer med',
        'title_highlight' => 'dina favoritverktyg',
        'subtitle' => 'Anslut din agent till Slack, HubSpot, Salesforce och 20+ verktyg via MCP och anpassade funktioner',
    ],

    'features_bar' => [
        'train_on_data' => 'Träna på din data',
        'live_in_minutes' => 'Live på minuter',
        'tool_calling' => 'Tool calling',
        'chat_with_data' => 'Chatta med din data',
        'white_label' => 'White-label',
        'deploy_anywhere' => 'Driftsätt var som helst',
        'custom_functions' => 'Anpassade funktioner',
        'mcp_servers' => 'MCP-servrar',
        'prev' => 'Föregående funktioner',
        'next' => 'Nästa funktioner',
    ],

    'cards' => [
        'header_title' => 'Din data. Dina verktyg. Dina agenter.',
        'header_subtitle' => 'Träna den på din data, anslut den till dina verktyg och se exakt vad som händer i varje konversation — med ditt team och dina kunder.',
        'card1_title' => 'Träna på din egen data',
        'card1_desc' => 'Ladda upp filer, crawla din webbplats eller lägg till Q&A-par och videor. Din agent svarar från ditt innehåll och håller sig uppdaterad när det förändras.',
        'card2_title' => 'Agerar i dina verktyg',
        'card2_desc' => 'Din agent svarar inte bara — den bokar möten, kontrollerar lager och skapar ärenden genom att anropa dina verktyg och API:er via MCP-servrar och anpassade funktioner.',
        'card3_title' => 'Avancerad rapportering',
        'card3_desc' => 'Ämnen, sentiment och användning i varje konversation. Se vad folk frågar om, hur agenten svarar och var det finns förbättringspotential.',
        'sources' => 'Källor',
        'add_source' => '+ Lägg till källa',
        'trained' => 'Tränad',
        'training' => 'Tränar',
        'done' => 'Klart',
        'online' => 'Online',
        'demo_pages' => '12 sidor · synkad för 2h sedan',
        'demo_crawled' => '128 sidor crawlade',
        'demo_product_faq' => 'Produkt-FAQ',
        'demo_book' => 'Boka en demo åt mig på tisdag eftermiddag.',
        'demo_booking' => 'Bokar demo · Tis 15:00',
        'demo_booked' => 'Klart — du är bokad på tisdag klockan 15:00.',
        'demo_topics' => 'Ämnen',
        'last_7_days' => 'Senaste 7 dagarna',
        'demo_account_setup' => 'Kontoinställning',
        'demo_positive' => '94% Positiva',
        'demo_negative' => '4% Negativa',
    ],

    'showcase' => [
        'title' => 'Se det i action',
        'subtitle' => 'Riktiga skärmdumpar från produkten — från första inbäddningen till revisionsspåret.',
        'tabs' => [
            'widget' => [
                'tab_name' => 'Chattwidget',
                'title' => 'Driftsätt din agent var som helst',
                'description' => 'Bädda in den som en widget, dela den som en helsideshostad chatt, lägg till en ⌘K-kommandorad eller nå användare på Telegram och Slack. Samma agent överallt — svarar från din data och dina verktyg, här med live-nyheter från webben mitt i konversationen.',
            ],
            'connectors' => [
                'tab_name' => 'Anslutningar',
                'title' => 'Koppla din agent till dina verktyg',
                'description' => 'Ett klick ansluter Perplexity-webbsökning, Slack, Gmail, Stripe och 25+ andra verktyg via MCP. Din agent slutar bara svara och börjar faktiskt göra saker.',
            ],
            'control_panel' => [
                'tab_name' => 'Kontrollpanel',
                'title' => 'Hantera alla agenter på ett ställe',
                'description' => 'Bygg och kör alla dina AI-agenter från en instrumentpanel. Konfigurera beteende, anslut verktyg, välj modeller och styr varje agent i din workspace.',
            ],
            'inbox' => [
                'tab_name' => 'Inkorg',
                'title' => 'Komplett konversationshistorik',
                'description' => 'Granska varje konversation med sentiment, ämne och land i en överblick. Identifiera luckor i din data, kliv in med live-handoff och förfina din agent där det spelar roll.',
            ],
            'pulse' => [
                'tab_name' => 'Pulse',
                'title' => 'Känn pulsen i varje konversation',
                'description' => 'Varje konversation får en puls — hur den startade, hur den slutade och var den toppade. Fånga en frustrerad användare innan de ger upp, inte efteråt.',
            ],
            'analytics' => [
                'tab_name' => 'Analys',
                'title' => 'Analys & insikter',
                'description' => 'Spåra användning, sentiment, ton och ämnen i varje konversation. Se exakt hur dina agenter presterar och fatta datadrivna beslut för att förbättra dem.',
            ],
            'governance' => [
                'tab_name' => 'Styrning',
                'title' => 'Varje verktygsanrop, dokumenterat',
                'description' => 'En oföränderlig revisionslogg över varje åtgärd dina agenter vidtar — vilket verktyg, vilka argument, vilket utfall, hur snabbt. Compliance-redo som standard.',
            ],
            'leads' => [
                'tab_name' => 'Leads',
                'title' => 'Fånga leads i konversationen',
                'description' => 'Låt din agent fånga och kvalificera leads mitt i konversationen och sedan skicka dem till ditt CRM via en MCP-server eller en anpassad funktion.',
            ],
        ],
    ],

    'steps' => [
        'title' => 'Från idé till live-agent på minuter',
        'create_title' => 'Skapa',
        'create_desc' => 'Skapa en agent med ett klick — ge den ett namn, välj en modell och bestäm hur du vill driftsätta den.',
        'train_title' => 'Träna & anslut',
        'train_desc' => 'Träna den på din data — ladda upp dokument, crawla din webbplats och anslut MCP-servrar och anpassade funktioner så att den kan agera i dina verktyg.',
        'deploy_title' => 'Driftsätt',
        'deploy_desc' => 'Driftsätt var som helst — bädda in den, dela en hostad länk eller anropa den från din app. Din agent är live och jobbar i dina befintliga verktyg.',
    ],

    'pricing' => [
        'heading' => 'Priser',
        'pay_monthly' => 'Betala månadsvis',
        'pay_yearly' => 'Betala årsvis',
        'per_month' => '/månad',
        'billed_yearly_at' => 'Faktureras årsvis till',
        'custom' => 'Anpassat',
        'unlimited' => 'Obegränsat',
        'lets_talk' => 'Vi ses',
        'contact_sales' => 'Kontakta sälj',
        'start_free_trial' => 'Starta gratis provperiod',
        'comparison_caption' => 'Jämförelse av prisplaner',
        'price_sr' => 'Pris',
        'days' => 'dagar',
        'included_in' => 'Ingår i :plan',
        'not_included_in' => 'Ingår inte i :plan',
        'what_is' => 'Vad är :label?',
        'groups' => [
            'usage' => 'Användning',
            'features' => 'Funktioner',
            'security_support' => 'Säkerhet & support',
        ],
        'rows' => [
            'messages' => 'Meddelanden / månad',
            'agents' => 'AI-agenter',
            'pages' => 'Kunskapssidor',
            'links' => 'Webbplatslänkar',
            'minutes' => 'Ljud- / videominuter',
            'team_members' => 'Teammedlemmar',
            'teams' => 'Team',
            'chat_history' => 'Chatthistorik',
            'sentiment' => 'Sentimentanalys',
            'tone' => 'Tonanalys',
            'topics' => 'Ämnesanalys',
            'pulse' => 'Konversationspuls',
            'handoff' => 'Överlämning till människa',
            'api' => 'API-åtkomst',
            'mcp' => 'MCP',
            'function_calling' => 'Funktionsanrop',
            'integrations' => 'Anpassade integrationer',
            'white_label' => 'White-label (ta bort varumärke)',
            'sso' => 'SSO / SAML',
            'sla' => 'Drifttids-SLA',
            'support' => 'Dedikerad support',
            'dpa' => 'Anpassat avtal & DPA',
        ],
        'tips' => [
            'chat_history' => 'Hur länge varje konversation sparas innan den raderas automatiskt.',
            'sentiment' => 'Identifierar automatiskt om en konversation känns positiv, neutral eller negativ.',
            'tone' => 'Läser av den känslomässiga tonen i en chatt så att dina svar kan hålla varumärkets röst.',
            'topics' => 'Grupperar konversationer efter ämne så att du kan se vad folk frågar mest om.',
            'pulse' => 'En live-hälsopoäng för varje konversation som kombinerar sentiment och ton för att flagga chattar som behöver uppmärksamhet.',
            'handoff' => 'Låt en mänsklig handläggare kliva in och ta över från AI:n under en live-chatt.',
            'api' => 'Anslut Chatvia till dina egna appar och arbetsflöden via vårt API.',
            'mcp' => 'Anslut externa verktyg och data till din agent med Model Context Protocol.',
            'function_calling' => 'Låt din agent anropa dina egna funktioner och webhooks för att vidta åtgärder under en chatt.',
            'integrations' => 'Koppla dina egna verktyg och tjänster för att utöka vad dina agenter kan göra.',
            'white_label' => 'Dölj "Powered by Chatvia"-märkningen i din widget och på dina chattsidor.',
            'sso' => 'Låt ditt team logga in via er identitetsleverantör, som Okta eller Azure AD.',
            'sla' => 'En skriftlig garanti för hur ofta tjänsten är tillgänglig.',
            'support' => 'En namngiven kontaktperson och snabbare svarstider för ditt team.',
            'dpa' => 'Ett skräddarsytt avtal och villkor för databehandling för ditt juridikteam.',
        ],
    ],

    'faqs' => [
        'heading' => 'Vanliga frågor',
        'subtitle' => 'Allt du behöver veta om att bygga AI-agenter med Chatvia.',
        'still_questions' => 'Har du fler frågor?',
        'talk_to_ai' => 'Prata med vår AI',
        'items' => [
            'what' => [
                'question' => 'Vad är Chatvia?',
                'answer' => 'Chatvia är en B2B-plattform för att bygga och träna egna AI-agenter på din data. Dina agenter svarar från din kunskap och agerar i dina verktyg — anslut MCP-servrar, anropa dina API:er med anpassade funktioner och driftsätt var som helst.',
            ],
            'support_bot' => [
                'question' => 'Är Chatvia bara en supportchattbot?',
                'answer' => 'Nej. Chatvia är till för att bygga AI-agenter för vilket arbetsflöde som helst — interna kunskapsassistenter, forskningsverktyg, datauppslagningar, boknings- och beställningsflöden och mycket mer. Kundsupport är ett användningsfall du kan bygga, inte hela produkten.',
            ],
            'apis' => [
                'question' => 'Kan mina agenter anropa våra API:er och verktyg?',
                'answer' => 'Ja. Definiera anpassade funktioner (webhooks) så att en agent kan anropa valfri HTTP-endpoint, och anslut MCP-servrar för att ge den hela verktygssatser på en gång. Agenten bestämmer själv när den ska anropa dem under en konversation för att hämta data eller vidta åtgärder.',
            ],
            'mcp' => [
                'question' => 'Vad är MCP, och kan jag ansluta mina egna servrar?',
                'answer' => 'MCP (Model Context Protocol) är en öppen standard för att ge agenter verktyg. Du kan ansluta vilken fjärr-MCP-server som helst på sekunder och välja vilka verktyg varje agent får använda — inklusive dina egna interna servrar.',
            ],
            'training' => [
                'question' => 'Vad innebär träning?',
                'answer' => 'Träning matar din data genom en hämtningspipeline (RAG) så att agenten förstår den och svarar från ditt riktiga innehåll istället för att gissa. Vi tränar aldrig tredjepartsmodeller på din data, så den förblir privat.',
            ],
            'sources' => [
                'question' => 'Kan jag träna på min webbplats, dokument, ljud och video?',
                'answer' => 'Ja. Ladda upp dokument (PDF, Word, Text), crawla din webbplats eller anslut datakällor — och ljud och video transkriberas automatiskt. Din agent lär sig från ditt innehåll och håller sig uppdaterad när det förändras.',
            ],
            'models' => [
                'question' => 'Vilka AI-modeller använder Chatvia?',
                'answer' => 'Du kan välja den bästa modellen för varje agent och byta när som helst.',
            ],
            'agent_count' => [
                'question' => 'Hur många AI-agenter kan jag skapa?',
                'answer' => 'Det beror på din plan. Se prissektionen för antalet agenter som ingår i respektive plan.',
            ],
            'white_label' => [
                'question' => 'Kan jag anpassa och white-label-märka mina agenter?',
                'answer' => 'Ja. Anpassa varje agents namn, persona, varumärke och logotyp, och servera den på din egen domän för en helt white-label-upplevelse.',
            ],
            'security' => [
                'question' => 'Är min data säker och privat?',
                'answer' => 'Chatvia är GDPR-kompatibel, hostad i EU och använder säkerhet på företagsnivå. Vi tränar aldrig tredjepartsmodeller på din data, och dina dokument och konversationer förblir privata och krypterade.',
            ],
            'team' => [
                'question' => 'Erbjuder ni teamsamarbete?',
                'answer' => 'Ja. Alla planer inkluderar teamsamarbete — bjud in teammedlemmar och dela agenter inom ditt team.',
            ],
            'payment' => [
                'question' => 'Vilka betalningsmetoder accepterar ni?',
                'answer' => 'Vi accepterar alla vanliga kreditkort (Visa, MasterCard, American Express) och PayPal, hanterat säkert via Stripe. Uppgradera, nedgradera eller avsluta när som helst.',
            ],
        ],
    ],

    'cta' => [
        'headline_pre' => 'Lansera din första AI-agent på',
        'headline_highlight' => 'minuter',
        'description' => 'Bygg agenter tränade på din data som ansluter MCP-servrar, anropar dina API:er och agerar i dina befintliga verktyg — ingen kod, inga utvecklare.',
        'button' => 'Starta din gratis provperiod',
        'talk_to_ai' => 'Prata med vår AI',
    ],

    'footer' => [
        'terms' => 'Användarvillkor',
        'privacy' => 'Integritetspolicy',
        'refund' => 'Återbetalningspolicy',
        'subprocessors' => 'Underbiträden',
        'made_with_love' => 'Made with ❤️ in Denmark',
    ],

    'cookie_banner' => [
        'message' => 'Vi använder nödvändiga cookies för att hålla sajten igång. Inga spårningscookies.',
        'accept' => 'Förstått',
        'privacy' => 'Integritetspolicy',
    ],

];
