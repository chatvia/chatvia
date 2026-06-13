<?php

// Public marketing homepage (resources/views/landing/*). English is the source of
// truth and the default locale, so this file mirrors the copy shown on the site —
// keep it in sync with lang/ar/landing.php (same keys).

return [

    'meta' => [
        'title' => 'Chatvia.ai - Byg og træn AI-agenter på dine data | EU-hostet',
        'description' => 'Chatvia er B2B-platformen til at bygge, træne og deploye AI-agenter på dine egne data. Tilslut MCP-servere, kald dine API\'er med brugerdefinerede funktioner, og lad agenter handle i dine værktøjer. EU-hostet og GDPR-kompatibel.',
        'og_title' => 'Chatvia.ai - Byg og træn dine egne AI-agenter',
        'og_description' => 'Giv dit team AI-agenter, der kender dine data og handler i dine værktøjer — MCP-servere, brugerdefinerede funktioner og tool calling. Fra idé til live agent på minutter. EU-hostet og GDPR-kompatibel.',
    ],

    'nav' => [
        'home' => 'Forside',
        'features' => 'Funktioner',
        'pricing' => 'Priser',
        'mission' => 'Mission',
        'security' => 'Sikkerhed',
        'dpa' => 'DPA',
        'dashboard' => 'Dashboard',
        'logout' => 'Log ud',
        'login' => 'Log ind',
        'get_started' => 'Kom i gang',
        'menu' => 'Menu',
        'language' => 'Sprog',
    ],

    'hero' => [
        'badge' => 'AI-agenter, der kender din forretning til bunds',
        'title_line1' => 'Det er svært at bygge AI-agenter.',
        'title_make' => 'Vi gør det til et spørgsmål om',
        'title_minutes' => 'minutter',
        'subtitle' => 'Opret AI-agenter, der booker møder, tjekker lagerstatus og udløser arbejdsgange — med alt, hvad de ved om din forretning.',
        'no_code' => 'Ingen kode, ingen udviklere.',
        'eu_hosting' => 'Fuldt hostet i EU. Ingen databehandling i USA.',
        'gdpr_alt' => 'GDPR-kompatibel',
        'cta' => 'Start 7-dages gratis prøveperiode',
    ],

    'integrations' => [
        'eyebrow' => 'Forstærk din agent',
        'title' => 'Direkte integrationer med',
        'title_highlight' => 'dine foretrukne værktøjer',
        'subtitle' => 'Tilslut din agent til Slack, HubSpot, Salesforce og 20+ værktøjer via MCP og brugerdefinerede funktioner',
    ],

    'features_bar' => [
        'train_on_data' => 'Træn på dine data',
        'live_in_minutes' => 'Live på minutter',
        'tool_calling' => 'Tool calling',
        'chat_with_data' => 'Chat med dine data',
        'white_label' => 'White-label',
        'deploy_anywhere' => 'Deploy overalt',
        'custom_functions' => 'Brugerdefinerede funktioner',
        'mcp_servers' => 'MCP-servere',
        'prev' => 'Forrige funktioner',
        'next' => 'Næste funktioner',
    ],

    'cards' => [
        'header_title' => 'Dine data. Dine værktøjer. Dine agenter.',
        'header_subtitle' => 'Træn den på dine data, tilslut den til dine værktøjer, og se præcis, hvad der sker i hver samtale — med dit team og dine kunder.',
        'card1_title' => 'Træn på dine egne data',
        'card1_desc' => 'Upload filer, crawler dit website eller tilføj spørgsmål og svar samt videoer. Din agent svarer ud fra dit indhold og holder sig opdateret, efterhånden som det ændrer sig.',
        'card2_title' => 'Handler i dine værktøjer',
        'card2_desc' => 'Din agent svarer ikke bare — den booker møder, tjekker lagerstatus og opretter sager ved at kalde dine værktøjer og API\'er via MCP-servere og brugerdefinerede funktioner.',
        'card3_title' => 'Avanceret rapportering',
        'card3_desc' => 'Emner, sentiment og brug på tværs af alle samtaler. Se, hvad folk spørger om, hvordan agenten svarer, og hvor der er plads til forbedring.',
        'sources' => 'Kilder',
        'add_source' => '+ Tilføj kilde',
        'trained' => 'Trænet',
        'training' => 'Træner',
        'done' => 'Færdig',
        'online' => 'Online',
        'demo_pages' => '12 sider · synkroniseret for 2 t siden',
        'demo_crawled' => '128 sider crawlet',
        'demo_product_faq' => 'Produkt-FAQ',
        'demo_book' => 'Book mig en demo tirsdag eftermiddag.',
        'demo_booking' => 'Booker demo · Tirs 15:00',
        'demo_booked' => 'Klaret — du er booket til tirsdag kl. 15:00.',
        'demo_topics' => 'Emner',
        'last_7_days' => 'Seneste 7 dage',
        'demo_account_setup' => 'Kontoopsætning',
        'demo_positive' => '94 % positiv',
        'demo_negative' => '4 % negativ',
    ],

    'showcase' => [
        'title' => 'Se det i aktion',
        'subtitle' => 'Rigtige skærmbilleder fra produktet — fra første embed til revisionssporet.',
        'tabs' => [
            'widget' => [
                'tab_name' => 'Chat-widget',
                'title' => 'Deploy din agent overalt',
                'description' => 'Embed den som en widget, del den som en hosted chat-side, indsæt en ⌘K-kommandolinje, eller nå folk på Telegram og Slack. Samme agent overalt — svarer fra dine data og dine værktøjer, her med live nyheder fra nettet midt i samtalen.',
            ],
            'connectors' => [
                'tab_name' => 'Connectors',
                'title' => 'Tilslut din agent til dine værktøjer',
                'description' => 'Et klik forbinder Perplexity-websøgning, Slack, Gmail, Stripe og 25+ andre værktøjer via MCP. Din agent holder ikke bare op med at svare — den begynder at handle.',
            ],
            'control_panel' => [
                'tab_name' => 'Kontrolpanel',
                'title' => 'Administrer alle agenter ét sted',
                'description' => 'Byg og kør alle dine AI-agenter fra ét dashboard. Konfigurer adfærd, tilslut værktøjer, vælg modeller og styr hver agent på tværs af dit workspace.',
            ],
            'inbox' => [
                'tab_name' => 'Indbakke',
                'title' => 'Komplet samtaleoversigt',
                'description' => 'Gennemgå alle samtaler med sentiment, emne og land på et øjeblik. Spot huller i dine data, spring ind med live handoff, og finpuds din agent, der hvor det betyder noget.',
            ],
            'pulse' => [
                'tab_name' => 'Pulse',
                'title' => 'Mærk pulsen i hver samtale',
                'description' => 'Hver samtale får en puls — hvordan den startede, hvordan den sluttede, og hvor den toppede. Opdag en frustreret bruger, inden de giver op, ikke bagefter.',
            ],
            'analytics' => [
                'tab_name' => 'Analytics',
                'title' => 'Analytics og indsigter',
                'description' => 'Spor brug, sentiment, tone og emner på tværs af alle samtaler. Se præcis, hvordan dine agenter performer, og træf datadrevne beslutninger for at forbedre dem.',
            ],
            'governance' => [
                'tab_name' => 'Governance',
                'title' => 'Ethvert tool-kald, på skrift',
                'description' => 'Et uforanderligt revisionslog over alle handlinger dine agenter foretager — hvilket værktøj, hvilke argumenter, hvilket resultat, og hvor hurtigt. Klar til compliance fra starten.',
            ],
            'leads' => [
                'tab_name' => 'Leads',
                'title' => 'Fang leads i samtalen',
                'description' => 'Lad din agent fange og kvalificere leads midt i samtalen, og push dem til dit CRM via en MCP-server eller en brugerdefineret funktion.',
            ],
        ],
    ],

    'steps' => [
        'title' => 'Fra idé til live agent på minutter',
        'create_title' => 'Opret',
        'create_desc' => 'Opret en agent med et klik — giv den et navn, vælg en model, og bestem, hvordan du vil deploye den.',
        'train_title' => 'Træn og tilslut',
        'train_desc' => 'Træn den på dine data — upload dokumenter, crawler dit site, og tilslut MCP-servere og brugerdefinerede funktioner, så den kan handle i dine værktøjer.',
        'deploy_title' => 'Deploy',
        'deploy_desc' => 'Deploy overalt — embed den, del et hosted link, eller kald den fra din app. Din agent er live og virker i dine eksisterende værktøjer.',
    ],

    'pricing' => [
        'heading' => 'Priser',
        'pay_monthly' => 'Betal månedligt',
        'pay_yearly' => 'Betal årligt',
        'per_month' => '/måned',
        'billed_yearly_at' => 'Faktureres årligt til',
        'custom' => 'Tilpasset',
        'unlimited' => 'Ubegrænset',
        'lets_talk' => 'Lad os tale',
        'contact_sales' => 'Kontakt salg',
        'start_free_trial' => 'Start gratis prøveperiode',
        'comparison_caption' => 'Sammenligning af prisplaner',
        'price_sr' => 'Pris',
        'days' => 'dage',
        'included_in' => 'Inkluderet i :plan',
        'not_included_in' => 'Ikke inkluderet i :plan',
        'what_is' => 'Hvad er :label?',
        'groups' => [
            'usage' => 'Forbrug',
            'features' => 'Funktioner',
            'security_support' => 'Sikkerhed og support',
        ],
        'rows' => [
            'messages' => 'Beskeder pr. måned',
            'agents' => 'AI-agenter',
            'pages' => 'Vidensbase-sider',
            'links' => 'Website-links',
            'minutes' => 'Audio-/videominutter',
            'team_members' => 'Teammedlemmer',
            'teams' => 'Teams',
            'chat_history' => 'Chathistorik',
            'sentiment' => 'Sentimentanalyse',
            'tone' => 'Toneanalyse',
            'topics' => 'Emneanalyse',
            'pulse' => 'Samtale-Pulse',
            'handoff' => 'Overdragelse til mennesker',
            'api' => 'API-adgang',
            'mcp' => 'MCP',
            'function_calling' => 'Function calling',
            'integrations' => 'Brugerdefinerede integrationer',
            'white_label' => 'White-label (fjern branding)',
            'sso' => 'SSO / SAML',
            'sla' => 'Oppetids-SLA',
            'support' => 'Dedikeret support',
            'dpa' => 'Tilpasset kontrakt og DPA',
        ],
        'tips' => [
            'chat_history' => 'Hvor længe hver samtale gemmes, inden den automatisk slettes.',
            'sentiment' => 'Registrerer automatisk, om hver samtale føles positiv, neutral eller negativ.',
            'tone' => 'Aflæser den følelsesmæssige tone i en chat, så dine svar kan holde sig on-brand.',
            'topics' => 'Grupperer samtaler efter emne, så du kan se, hvad folk spørger mest om.',
            'pulse' => 'En live sundhedsscore for hver samtale, der kombinerer sentiment og tone for at markere chats, der kræver opmærksomhed.',
            'handoff' => 'Lad en menneskelig agent træde ind og overtage fra AI\'en under en live chat.',
            'api' => 'Forbind Chatvia til dine egne apps og arbejdsgange via vores API.',
            'mcp' => 'Tilslut eksterne værktøjer og data til din agent ved hjælp af Model Context Protocol.',
            'function_calling' => 'Lad din agent kalde dine egne funktioner og webhooks for at udføre handlinger under en chat.',
            'integrations' => 'Tilslut dine egne værktøjer og tjenester for at udvide, hvad dine agenter kan gøre.',
            'white_label' => 'Skjul "Powered by Chatvia"-mærket på tværs af din widget og chat-sider.',
            'sso' => 'Lad dit team logge ind via din virksomheds identity provider, f.eks. Okta eller Azure AD.',
            'sla' => 'En skriftlig garanti for, hvor tilgængeligt servicen er.',
            'support' => 'En fast kontaktperson og hurtigere svartider til dit team.',
            'dpa' => 'En skræddersyet aftale og databehandlingsvilkår til dit juridiske team.',
        ],
    ],

    'faqs' => [
        'heading' => 'Ofte stillede spørgsmål',
        'subtitle' => 'Alt, du har brug for at vide om at bygge AI-agenter med Chatvia.',
        'still_questions' => 'Har du stadig spørgsmål?',
        'talk_to_ai' => 'Tal med vores AI',
        'items' => [
            'what' => [
                'question' => 'Hvad er Chatvia?',
                'answer' => 'Chatvia er en B2B-platform til at bygge og træne dine egne AI-agenter på dine data. Dine agenter svarer fra din vidensbase og handler i dine værktøjer — tilslut MCP-servere, kald dine API\'er med brugerdefinerede funktioner, og deploy overalt.',
            ],
            'support_bot' => [
                'question' => 'Er Chatvia bare en support-chatbot?',
                'answer' => 'Nej. Chatvia er til at bygge AI-agenter til enhver arbejdsgang — interne videnassistenter, research-værktøjer, dataopslag, booking- og bestillingsflows og meget mere. Kundesupport er ét brugsscenarie, du kan bygge, ikke hele produktet.',
            ],
            'apis' => [
                'question' => 'Kan mine agenter kalde vores API\'er og værktøjer?',
                'answer' => 'Ja. Definer brugerdefinerede funktioner (webhooks), så en agent kan kalde ethvert HTTP-endpoint, og tilslut MCP-servere for at give den hele sæt af værktøjer på én gang. Agenten beslutter selv, hvornår den skal kalde dem under en samtale for at hente data eller udføre handlinger.',
            ],
            'mcp' => [
                'question' => 'Hvad er MCP, og kan jeg tilslutte mine egne servere?',
                'answer' => 'MCP (Model Context Protocol) er en åben standard til at give agenter værktøjer. Du kan tilslutte enhver fjern-MCP-server på sekunder og vælge, hvilke værktøjer hver agent må bruge — herunder dine egne interne servere.',
            ],
            'training' => [
                'question' => 'Hvad betyder træning?',
                'answer' => 'Træning sender dine data igennem en retrieval-pipeline (RAG), så agenten forstår dem og svarer ud fra dit reelle indhold i stedet for at gætte. Vi træner aldrig tredjeparts-modeller på dine data, så de forbliver private.',
            ],
            'sources' => [
                'question' => 'Kan jeg træne på mit website, dokumenter, lyd og video?',
                'answer' => 'Ja. Upload dokumenter (PDF, Word, tekst), crawler dit website eller tilslut datakilder — og lyd og video transskriberes automatisk. Din agent lærer af dit indhold og holder sig opdateret, efterhånden som det ændrer sig.',
            ],
            'models' => [
                'question' => 'Hvilke AI-modeller bruger Chatvia?',
                'answer' => 'Du kan vælge den bedste model til hver agent og skifte til enhver tid.',
            ],
            'agent_count' => [
                'question' => 'Hvor mange AI-agenter kan jeg oprette?',
                'answer' => 'Det afhænger af din plan. Se prisafsnittet for antallet af agenter inkluderet i hver plan.',
            ],
            'white_label' => [
                'question' => 'Kan jeg tilpasse og white-label mine agenter?',
                'answer' => 'Ja. Tilpas hver agents navn, persona, branding og logo, og server den på dit eget domæne for en fuldt white-label oplevelse.',
            ],
            'security' => [
                'question' => 'Er mine data sikre og private?',
                'answer' => 'Chatvia er GDPR-kompatibel, hostet i EU og bruger sikkerhed i enterprise-klasse. Vi træner aldrig tredjeparts-modeller på dine data, og dine dokumenter og samtaler forbliver private og krypterede.',
            ],
            'team' => [
                'question' => 'Tilbyder I teamsamarbejde?',
                'answer' => 'Ja. Alle planer inkluderer teamsamarbejde — inviter teammedlemmer og del agenter på tværs af dit team.',
            ],
            'payment' => [
                'question' => 'Hvilke betalingsmetoder accepterer I?',
                'answer' => 'Vi accepterer alle større kreditkort (Visa, MasterCard, American Express) og PayPal, behandlet sikkert via Stripe. Opgrader, nedgrader eller opsig til enhver tid.',
            ],
        ],
    ],

    'cta' => [
        'headline_pre' => 'Ship din første AI-agent på',
        'headline_highlight' => 'minutter',
        'description' => 'Byg agenter trænet på dine data, der tilslutter MCP-servere, kalder dine API\'er og handler i dine eksisterende værktøjer — ingen kode, ingen udviklere.',
        'button' => 'Start din gratis prøveperiode',
        'talk_to_ai' => 'Tal med vores AI',
    ],

    'footer' => [
        'terms' => 'Servicevilkår',
        'privacy' => 'Privatlivspolitik',
        'refund' => 'Refusionspolitik',
        'subprocessors' => 'Underdatabehandlere',
        'made_with_love' => 'Made with ❤️ in Denmark',
    ],

    'cookie_banner' => [
        'message' => 'Vi bruger nødvendige cookies for at få hjemmesiden til at fungere. Ingen sporingscookies.',
        'accept' => 'Forstået',
        'privacy' => 'Privatlivspolitik',
    ],

];
