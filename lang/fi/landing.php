<?php

// Public marketing homepage (resources/views/landing/*). English is the source of
// truth and the default locale, so this file mirrors the copy shown on the site —
// keep it in sync with lang/en/landing.php (same keys).

return [

    'meta' => [
        'title' => 'Chatvia.ai - Rakenna ja kouluta tekoälyagentteja omalle datallesi | EU-isännöity',
        'description' => 'Chatvia on B2B-alusta tekoälyagenttien rakentamiseen, kouluttamiseen ja käyttöönottoon omalla datallasi. Yhdistä MCP-palvelimet, kutsu omia API-rajapintojasi mukautetuilla funktioilla ja anna agenttien toimia työkaluissasi. EU-isännöity ja GDPR-yhteensopiva.',
        'og_title' => 'Chatvia.ai - Rakenna ja kouluta omat tekoälyagenttisi',
        'og_description' => 'Anna tiimillesi tekoälyagentit, jotka tuntevat datasi ja toimivat työkaluissasi — MCP-palvelimet, mukautetut funktiot ja työkutsu. Ideasta toimivaksi agentiksi minuuteissa. EU-isännöity ja GDPR-yhteensopiva.',
    ],

    'nav' => [
        'home' => 'Etusivu',
        'features' => 'Ominaisuudet',
        'pricing' => 'Hinnoittelu',
        'mission' => 'Missio',
        'security' => 'Tietoturva',
        'dpa' => 'DPA',
        'dashboard' => 'Hallintapaneeli',
        'logout' => 'Kirjaudu ulos',
        'login' => 'Kirjaudu sisään',
        'get_started' => 'Aloita',
        'menu' => 'Valikko',
        'language' => 'Kieli',
    ],

    'hero' => [
        'badge' => 'Tekoälyagentit, jotka tuntevat liiketoimintasi läpikotaisin',
        'title_line1' => 'Tekoälyagenttien rakentaminen on vaikeaa.',
        'title_make' => 'Me teemme siitä',
        'title_minutes' => 'minuuttien',
        'subtitle' => 'Luo tekoälyagentit, jotka varaavat kokouksia, tarkistavat varaston ja käynnistävät työnkulkuja — hyödyntäen kaiken tietämyksensä liiketoiminnastasi.',
        'no_code' => 'Ei koodia, ei insinöörejä.',
        'eu_hosting' => 'Täysin EU:ssa isännöity. Nolla USA:n tietojenkäsittelyä.',
        'gdpr_alt' => 'GDPR-yhteensopiva',
        'cta' => 'Aloita 7 päivän ilmainen kokeilu',
    ],

    'integrations' => [
        'eyebrow' => 'Tehosta agenttiasi',
        'title' => 'Suorat integraatiot',
        'title_highlight' => 'suosikkityökaluihisi',
        'subtitle' => 'Yhdistä agenttisi Slackiin, HubSpotiin, Salesforceen ja yli 20 muuhun työkaluun MCP:n ja mukautettujen funktioiden avulla',
    ],

    'features_bar' => [
        'train_on_data' => 'Kouluta datallasi',
        'live_in_minutes' => 'Toiminnassa minuuteissa',
        'tool_calling' => 'Työkutsu',
        'chat_with_data' => 'Keskustele datasi kanssa',
        'white_label' => 'White-label',
        'deploy_anywhere' => 'Käytä missä tahansa',
        'custom_functions' => 'Mukautetut funktiot',
        'mcp_servers' => 'MCP-palvelimet',
        'prev' => 'Edelliset ominaisuudet',
        'next' => 'Seuraavat ominaisuudet',
    ],

    'cards' => [
        'header_title' => 'Sinun datasi. Sinun työkalusi. Sinun agenttisi.',
        'header_subtitle' => 'Kouluta se datallasi, yhdistä se työkaluihisi ja katso tarkalleen mitä tapahtuu jokaisessa keskustelussa — tiimisi ja asiakkaidesi kanssa.',
        'card1_title' => 'Kouluta omalla datallasi',
        'card1_desc' => 'Lataa tiedostoja, indeksoi verkkosivustosi tai lisää kysymys-vastaus-pareja ja videoita. Agenttisi vastaa sisällöstäsi ja pysyy synkronoituna muutosten mukana.',
        'card2_title' => 'Toimii työkaluissasi',
        'card2_desc' => 'Agenttisi ei ainoastaan vastaa — se varaa kokouksia, tarkistaa varaston ja luo tikettejä kutsumalla työkalujasi ja API-rajapintojasi MCP-palvelimien ja mukautettujen funktioiden kautta.',
        'card3_title' => 'Edistynyt raportointi',
        'card3_desc' => 'Aiheet, tunnelma ja käyttö kaikissa keskusteluissa. Katso mitä ihmiset kysyvät, miten agentti vastaa ja missä on parannettavaa.',
        'sources' => 'Lähteet',
        'add_source' => '+ Lisää lähde',
        'trained' => 'Koulutettu',
        'training' => 'Koulutetaan',
        'done' => 'Valmis',
        'online' => 'Verkossa',
        'demo_pages' => '12 sivua · synkronoitu 2 t sitten',
        'demo_crawled' => '128 sivua indeksoitu',
        'demo_product_faq' => 'Tuotteen UKK',
        'demo_book' => 'Varaa minulle demo tiistai-iltapäiväksi.',
        'demo_booking' => 'Varataan demo · Ti 15:00',
        'demo_booked' => 'Valmis — sinut on varattu tiistaina klo 15:00.',
        'demo_topics' => 'Aiheet',
        'last_7_days' => 'Viimeiset 7 päivää',
        'demo_account_setup' => 'Tilin asetukset',
        'demo_positive' => '94% Positiivinen',
        'demo_negative' => '4% Negatiivinen',
    ],

    'showcase' => [
        'title' => 'Katso se toiminnassa',
        'subtitle' => 'Oikeita kuvakaappauksia tuotteesta — ensimmäisestä upotuksesta tilintarkastusketjuun.',
        'tabs' => [
            'widget' => [
                'tab_name' => 'Chat-widget',
                'title' => 'Ota agentti käyttöön missä tahansa',
                'description' => 'Upota se widgetiksi, jaa se koko sivun isännöitynä chattinä, lisää ⌘K-komentopalkki tai tavoita ihmiset Telegramissa ja Slackissa. Sama agentti kaikkialla — vastaa datastasi ja työkaluistasi, tässä hakien reaaliaikaisia uutisia verkosta kesken keskustelun.',
            ],
            'connectors' => [
                'tab_name' => 'Yhdysliittymät',
                'title' => 'Liitä agenttisi työkaluihisi',
                'description' => 'Yhdellä napsautuksella yhdistät Perplexity-verkkohaun, Slackin, Gmailin, Stripen ja yli 25 muuta työkalua MCP:n kautta. Agenttisi lakkaa vain vastaamasta ja alkaa toimia.',
            ],
            'control_panel' => [
                'tab_name' => 'Ohjauspaneeli',
                'title' => 'Hallinnoi kaikkia agentteja yhdessä paikassa',
                'description' => 'Rakenna ja hallinnoi kaikkia tekoälyagenttejasi yhdestä hallintapaneelista. Määritä toiminta, yhdistä työkalut, valitse mallit ja ohjaa kaikkia agentteja koko työtilassasi.',
            ],
            'inbox' => [
                'tab_name' => 'Saapuneet',
                'title' => 'Täydellinen keskusteluhistoria',
                'description' => 'Tarkastele jokaista keskustelua tunnelmineen, aiheineen ja maatietoineen yhdellä silmäyksellä. Huomaa puutteet datassasi, hyppää mukaan reaaliaikaisella siirtopalvelulla ja kehitä agenttiasi siellä missä sillä on merkitystä.',
            ],
            'pulse' => [
                'tab_name' => 'Pulssi',
                'title' => 'Tunnista jokaisen keskustelun pulssi',
                'description' => 'Jokainen keskustelu saa pulssimittauksen — miten se alkoi, miten se päättyi ja missä kohtasi huippunsa. Tartu turhautuneeseen käyttäjään ennen kuin hän luovuttaa — ei jälkeenpäin.',
            ],
            'analytics' => [
                'tab_name' => 'Analytiikka',
                'title' => 'Analytiikka ja oivallukset',
                'description' => 'Seuraa käyttöä, tunnelmaa, sävyä ja aiheita kaikissa keskusteluissa. Katso tarkalleen miten agenttisi suoriutuvat ja tee datapohjaisia päätöksiä niiden kehittämiseksi.',
            ],
            'governance' => [
                'tab_name' => 'Hallinto',
                'title' => 'Jokainen työkutsu dokumentoituna',
                'description' => 'Muuttumaton tilintarkastusloki kaikista agenttien toimenpiteistä — mikä työkalu, mitkä argumentit, mikä tulos, kuinka nopeasti. Valmiudessa vaatimustenmukaisuuteen oletusarvoisesti.',
            ],
            'leads' => [
                'tab_name' => 'Liidit',
                'title' => 'Kerää liidejä keskustelussa',
                'description' => 'Anna agenttisi kerätä ja kvalifioida liidejä kesken keskustelun, ja siirrä ne sitten CRM-järjestelmääsi MCP-palvelimen tai mukautetun funktion kautta.',
            ],
        ],
    ],

    'steps' => [
        'title' => 'Ideasta toimivaksi agentiksi minuuteissa',
        'create_title' => 'Luo',
        'create_desc' => 'Luo agentti yhdellä napsautuksella — anna sille nimi, valitse malli ja päätä miten haluat ottaa sen käyttöön.',
        'train_title' => 'Kouluta ja yhdistä',
        'train_desc' => 'Kouluta se datallasi — lataa asiakirjoja, indeksoi sivustosi ja yhdistä MCP-palvelimet ja mukautetut funktiot niin se voi toimia työkaluissasi.',
        'deploy_title' => 'Ota käyttöön',
        'deploy_desc' => 'Ota käyttöön missä tahansa — upota se, jaa isännöity linkki tai kutsu sitä sovelluksestasi. Agenttisi on toiminnassa olemassa olevissa työkaluissasi.',
    ],

    'pricing' => [
        'heading' => 'Hinnoittelu',
        'pay_monthly' => 'Maksa kuukausittain',
        'pay_yearly' => 'Maksa vuosittain',
        'per_month' => '/kk',
        'billed_yearly_at' => 'Laskutetaan vuosittain',
        'custom' => 'Mukautettu',
        'unlimited' => 'Rajaton',
        'lets_talk' => 'Otetaan yhteyttä',
        'contact_sales' => 'Ota yhteyttä myyntiin',
        'start_free_trial' => 'Aloita ilmainen kokeilu',
        'comparison_caption' => 'Hinnoittelusuunnitelmien vertailu',
        'price_sr' => 'Hinta',
        'days' => 'päivää',
        'included_in' => 'Sisältyy suunnitelmaan :plan',
        'not_included_in' => 'Ei sisälly suunnitelmaan :plan',
        'what_is' => 'Mikä on :label?',
        'groups' => [
            'usage' => 'Käyttö',
            'features' => 'Ominaisuudet',
            'security_support' => 'Tietoturva ja tuki',
        ],
        'rows' => [
            'messages' => 'Viestit / kk',
            'agents' => 'Tekoälyagentit',
            'pages' => 'Tietosivut',
            'links' => 'Verkkosivulinkit',
            'minutes' => 'Ääni-/videominuutit',
            'team_members' => 'Tiimin jäsenet',
            'teams' => 'Tiimit',
            'chat_history' => 'Keskusteluhistoria',
            'sentiment' => 'Tunneanalyysi',
            'tone' => 'Sävyanalyysi',
            'topics' => 'Aiheanalyysi',
            'pulse' => 'Keskustelupulssi',
            'handoff' => 'Siirto ihmiselle',
            'api' => 'API-käyttöoikeus',
            'mcp' => 'MCP',
            'function_calling' => 'Funktiokutsu',
            'integrations' => 'Mukautetut integraatiot',
            'white_label' => 'White-label (poista brändäys)',
            'sso' => 'SSO / SAML',
            'sla' => 'Toiminta-ajan SLA',
            'support' => 'Omistettu tuki',
            'dpa' => 'Mukautettu sopimus ja DPA',
        ],
        'tips' => [
            'chat_history' => 'Kuinka kauan jokainen keskustelu säilytetään ennen automaattista poistamista.',
            'sentiment' => 'Tunnistaa automaattisesti, onko jokainen keskustelu tunnelmaltaan positiivinen, neutraali vai negatiivinen.',
            'tone' => 'Lukee chatin tunnesävyn niin, että vastauksesi pysyvät brändin mukaisina.',
            'topics' => 'Ryhmittelee keskustelut aiheen mukaan, jotta näet mistä ihmiset kysyvät eniten.',
            'pulse' => 'Reaaliaikainen terveyspisteet jokaiselle keskustelulle, joka yhdistää tunnelman ja sävyn merkitsemään huomiota vaativat chatit.',
            'handoff' => 'Anna ihmisagentti astua mukaan ja ottaa tekoälytyn haltuun reaaliaikaisessa chatissa.',
            'api' => 'Yhdistä Chatvia omiin sovelluksiisi ja työnkulkuihin API:n kautta.',
            'mcp' => 'Yhdistä ulkoiset työkalut ja data agenttiisi Model Context Protocolin avulla.',
            'function_calling' => 'Anna agenttisi kutsua omia funktioitasi ja webhookejasi toimenpiteiden suorittamiseksi chatin aikana.',
            'integrations' => 'Yhdistä omat työkalusi ja palvelusi laajentamaan agenttien toimintamahdollisuuksia.',
            'white_label' => 'Piilota "Powered by Chatvia" -merkki widgetissäsi ja chat-sivuillasi.',
            'sso' => 'Anna tiimisi kirjautua sisään yrityksen identiteettipalveluntarjoajan, kuten Oktan tai Azure AD:n, kautta.',
            'sla' => 'Kirjallinen takuu palvelun saatavuudesta.',
            'support' => 'Nimetty yhteyshenkilö ja nopeammat vasteajat tiimillesi.',
            'dpa' => 'Räätälöity sopimus ja tietojenkäsittelyehdot lakitiimillesi.',
        ],
    ],

    'faqs' => [
        'heading' => 'Usein kysytyt kysymykset',
        'subtitle' => 'Kaikki mitä sinun tarvitsee tietää tekoälyagenttien rakentamisesta Chatvialla.',
        'still_questions' => 'Onko sinulla vielä kysymyksiä?',
        'talk_to_ai' => 'Keskustele tekoälymme kanssa',
        'items' => [
            'what' => [
                'question' => 'Mikä on Chatvia?',
                'answer' => 'Chatvia on B2B-alusta omien tekoälyagenttien rakentamiseen ja kouluttamiseen datallasi. Agenttisi vastaavat tietämyksestäsi ja toimivat työkaluissasi — yhdistä MCP-palvelimet, kutsu API-rajapintojasi mukautetuilla funktioilla ja ota käyttöön missä tahansa.',
            ],
            'support_bot' => [
                'question' => 'Onko Chatvia vain tukichatbot?',
                'answer' => 'Ei. Chatvia on tarkoitettu tekoälyagenttien rakentamiseen mihin tahansa työnkulkuun — sisäiset tietoassistentit, tutkimustyökalut, datan haut, varaus- ja tilausvirrat ja paljon muuta. Asiakastuki on yksi käyttötapaus jonka voit rakentaa, ei koko tuote.',
            ],
            'apis' => [
                'question' => 'Voivatko agentit kutsua API-rajapintojamme ja työkalujamme?',
                'answer' => 'Kyllä. Määritä mukautetut funktiot (webhookit), jotta agentti voi kutsua mitä tahansa HTTP-päätepistettä, ja yhdistä MCP-palvelimet antaaksesi sille kokonaisia työkalukokoelmia kerralla. Agentti päättää milloin kutsua niitä keskustelun aikana datan hakemiseksi tai toimenpiteen suorittamiseksi.',
            ],
            'mcp' => [
                'question' => 'Mikä on MCP ja voinko yhdistää omia palvelimiani?',
                'answer' => 'MCP (Model Context Protocol) on avoin standardi agenteille tarkoitettujen työkalujen antamiseen. Voit yhdistää minkä tahansa etä-MCP-palvelimen sekunneissa ja valita mitkä työkalut kukin agentti voi käyttää — mukaan lukien omat sisäiset palvelimesi.',
            ],
            'training' => [
                'question' => 'Mitä kouluttaminen tarkoittaa?',
                'answer' => 'Kouluttaminen syöttää datasi hakuputkilinjan (RAG) kautta, jotta agentti ymmärtää sen ja vastaa todellisesta sisällöstäsi arvailun sijaan. Emme koskaan kouluta kolmansien osapuolten malleja datallasi, joten se pysyy yksityisenä.',
            ],
            'sources' => [
                'question' => 'Voinko kouluttaa verkkosivustollani, asiakirjoilla, äänellä ja videolla?',
                'answer' => 'Kyllä. Lataa asiakirjoja (PDF, Word, teksti), indeksoi verkkosivustosi tai yhdistä tietolähteitä — ja ääni ja video transkriboidaan automaattisesti. Agenttisi oppii sisällöstäsi ja pysyy synkronoituna muutosten mukana.',
            ],
            'models' => [
                'question' => 'Mitä tekoälymalleja Chatvia käyttää?',
                'answer' => 'Voit valita parhaan mallin kullekin agentille ja vaihtaa milloin tahansa.',
            ],
            'agent_count' => [
                'question' => 'Kuinka monta tekoälyagenttia voin luoda?',
                'answer' => 'Se riippuu suunnitelmastasi. Katso hinnoitteluosiosta kunkin suunnitelman sisältämien agenttien määrä.',
            ],
            'white_label' => [
                'question' => 'Voinko mukauttaa ja white-label-merkitä agentit?',
                'answer' => 'Kyllä. Mukauta jokaisen agentin nimi, persoona, brändäys ja logo, ja tarjoa se omalla verkkotunnuksellasi täysin white-label-kokemuksen saavuttamiseksi.',
            ],
            'security' => [
                'question' => 'Onko datani turvassa ja yksityistä?',
                'answer' => 'Chatvia on GDPR-yhteensopiva, isännöity EU:ssa ja käyttää yritystason tietoturvaa. Emme koskaan kouluta kolmansien osapuolten malleja datallasi, ja asiakirjasi ja keskustelusi pysyvät yksityisinä ja salattuina.',
            ],
            'team' => [
                'question' => 'Tarjoatteko tiimityöskentelyä?',
                'answer' => 'Kyllä. Kaikki suunnitelmat sisältävät tiimityöskentelyn — kutsu tiimin jäseniä ja jaa agentit koko tiimillesi.',
            ],
            'payment' => [
                'question' => 'Mitä maksutapoja hyväksytte?',
                'answer' => 'Hyväksymme kaikki suurimmat luottokortit (Visa, MasterCard, American Express) ja PayPalin, jotka käsitellään turvallisesti Stripen kautta. Päivitä, alenna tai peruuta milloin tahansa.',
            ],
        ],
    ],

    'cta' => [
        'headline_pre' => 'Ota ensimmäinen tekoälyagenttisi käyttöön',
        'headline_highlight' => 'minuuteissa',
        'description' => 'Rakenna agentit, jotka on koulutettu datallasi, yhdistävät MCP-palvelimet, kutsuvat API-rajapintojasi ja toimivat olemassa olevissa työkaluissasi — ei koodia, ei insinöörejä.',
        'button' => 'Aloita ilmainen kokeilu',
        'talk_to_ai' => 'Keskustele tekoälymme kanssa',
    ],

    'footer' => [
        'terms' => 'Käyttöehdot',
        'privacy' => 'Tietosuoja',
        'refund' => 'Palautuskäytäntö',
        'subprocessors' => 'Alihankkijat',
        'made_with_love' => 'Made with ❤️ in Denmark',
    ],

    'cookie_banner' => [
        'message' => 'Käytämme välttämättömiä evästeitä sivuston toiminnan ylläpitämiseksi. Ei seurantaevästeitä.',
        'accept' => 'Selvä',
        'privacy' => 'Tietosuojakäytäntö',
    ],

];
