<?php

// Public marketing homepage (resources/views/landing/*). English is the source of
// truth and the default locale, so this file mirrors the copy shown on the site —
// keep it in sync with lang/ar/landing.php (same keys).

return [

    'meta' => [
        'title' => 'Chatvia.ai - Crea y Entrena Agentes de IA con Tus Datos | Alojado en la UE',
        'description' => 'Chatvia es la plataforma B2B para crear, entrenar y desplegar agentes de IA con tus propios datos. Conecta servidores MCP, llama a tus APIs con funciones personalizadas y deja que los agentes actúen en tus herramientas. Alojado en la UE y compatible con GDPR.',
        'og_title' => 'Chatvia.ai - Crea y Entrena Tus Propios Agentes de IA',
        'og_description' => 'Dale a tu equipo agentes de IA que conocen tus datos y actúan en tus herramientas — servidores MCP, funciones personalizadas y llamadas a herramientas. De la idea a un agente en producción en minutos. Alojado en la UE y compatible con GDPR.',
    ],

    'nav' => [
        'home' => 'Inicio',
        'features' => 'Funciones',
        'pricing' => 'Precios',
        'mission' => 'Misión',
        'security' => 'Seguridad',
        'dpa' => 'DPA',
        'dashboard' => 'Panel',
        'logout' => 'Cerrar sesión',
        'login' => 'Iniciar sesión',
        'get_started' => 'Comenzar',
        'menu' => 'Menú',
        'language' => 'Idioma',
    ],

    'hero' => [
        'badge' => 'Agentes de IA que conocen tu negocio a fondo',
        'title_line1' => 'Crear agentes de IA es difícil.',
        'title_make' => 'Nosotros lo hacemos en',
        'title_minutes' => 'minutos',
        'subtitle' => 'Crea agentes de IA que agendan reuniones, verifican el inventario y activan flujos de trabajo — usando todo lo que saben sobre tu negocio.',
        'no_code' => 'Sin código, sin ingenieros.',
        'eu_hosting' => 'Alojado completamente en la UE. Sin procesamiento de datos en EE. UU.',
        'gdpr_alt' => 'Compatible con GDPR',
        'cta' => 'Comenzar prueba gratuita de 7 días',
    ],

    'integrations' => [
        'eyebrow' => 'Potencia tu agente',
        'title' => 'Integraciones directas con',
        'title_highlight' => 'tus herramientas favoritas',
        'subtitle' => 'Conecta tu agente a Slack, HubSpot, Salesforce y más de 20 herramientas a través de MCP y funciones personalizadas',
    ],

    'features_bar' => [
        'train_on_data' => 'Entrena con tus datos',
        'live_in_minutes' => 'En producción en minutos',
        'tool_calling' => 'Llamadas a herramientas',
        'chat_with_data' => 'Chatea con tus datos',
        'white_label' => 'White-label',
        'deploy_anywhere' => 'Despliega en cualquier lugar',
        'custom_functions' => 'Funciones personalizadas',
        'mcp_servers' => 'Servidores MCP',
        'prev' => 'Funciones anteriores',
        'next' => 'Siguientes funciones',
    ],

    'cards' => [
        'header_title' => 'Tus datos. Tus herramientas. Tus agentes.',
        'header_subtitle' => 'Entrénalo con tus datos, conéctalo a tus herramientas y ve exactamente qué ocurre en cada conversación — con tu equipo y tus clientes.',
        'card1_title' => 'Entrena con tus propios datos',
        'card1_desc' => 'Sube archivos, rastrea tu sitio web o agrega pares de preguntas y respuestas y videos. Tu agente responde desde tu contenido y se mantiene actualizado cuando cambia.',
        'card2_title' => 'Actúa en tus herramientas',
        'card2_desc' => 'Tu agente no solo responde — agenda reuniones, verifica el inventario y crea tickets llamando a tus herramientas y APIs a través de servidores MCP y funciones personalizadas.',
        'card3_title' => 'Reportes avanzados',
        'card3_desc' => 'Temas, sentimiento y uso en cada conversación. Ve qué preguntan las personas, cómo responde el agente y dónde mejorar.',
        'sources' => 'Fuentes',
        'add_source' => '+ Agregar fuente',
        'trained' => 'Entrenado',
        'training' => 'Entrenando',
        'done' => 'Listo',
        'online' => 'En línea',
        'demo_pages' => '12 páginas · sincronizado hace 2h',
        'demo_crawled' => '128 páginas rastreadas',
        'demo_product_faq' => 'FAQ del producto',
        'demo_book' => 'Agéndame una demo para el martes por la tarde.',
        'demo_booking' => 'Agendando demo · Mar 3:00 PM',
        'demo_booked' => 'Listo — tienes la demo agendada para el martes a las 3:00 PM.',
        'demo_topics' => 'Temas',
        'last_7_days' => 'Últimos 7 días',
        'demo_account_setup' => 'Configuración de cuenta',
        'demo_positive' => '94% Positivo',
        'demo_negative' => '4% Negativo',
    ],

    'showcase' => [
        'title' => 'Míralo en acción',
        'subtitle' => 'Capturas reales del producto — desde el primer embed hasta el registro de auditoría.',
        'tabs' => [
            'widget' => [
                'tab_name' => 'Widget de chat',
                'title' => 'Despliega tu agente en cualquier lugar',
                'description' => 'Incorpóralo como widget, compártelo como chat hospedado en página completa, agrégalo como barra de comandos ⌘K, o llega a tus usuarios en Telegram y Slack. El mismo agente en todos lados — respondiendo con tus datos y tus herramientas, aquí obteniendo noticias en tiempo real de la web durante la conversación.',
            ],
            'connectors' => [
                'tab_name' => 'Conectores',
                'title' => 'Conecta tu agente a tus herramientas',
                'description' => 'Con un clic conectas la búsqueda web de Perplexity, Slack, Gmail, Stripe y más de 25 herramientas a través de MCP. Tu agente deja de solo responder y empieza a actuar.',
            ],
            'control_panel' => [
                'tab_name' => 'Panel de control',
                'title' => 'Gestiona todos tus agentes en un solo lugar',
                'description' => 'Crea y administra todos tus agentes de IA desde un solo panel. Configura el comportamiento, conecta herramientas, elige modelos y controla cada agente en tu workspace.',
            ],
            'inbox' => [
                'tab_name' => 'Bandeja de entrada',
                'title' => 'Historial completo de conversaciones',
                'description' => 'Revisa cada conversación con sentimiento, tema y país de un vistazo. Detecta vacíos en tus datos, intervén con handoff en vivo y mejora tu agente donde más importa.',
            ],
            'pulse' => [
                'tab_name' => 'Pulse',
                'title' => 'Siente el pulso de cada conversación',
                'description' => 'Cada conversación tiene su pulso — cómo comenzó, cómo terminó y dónde alcanzó su punto máximo. Detecta a un usuario frustrado antes de que abandone, no después.',
            ],
            'analytics' => [
                'tab_name' => 'Analíticas',
                'title' => 'Analíticas e insights',
                'description' => 'Monitorea el uso, el sentimiento, el tono y los temas en cada conversación. Ve exactamente cómo rinden tus agentes y toma decisiones basadas en datos para mejorarlos.',
            ],
            'governance' => [
                'tab_name' => 'Gobernanza',
                'title' => 'Cada llamada a herramienta, registrada',
                'description' => 'Un registro de auditoría inmutable de cada acción que realizan tus agentes — qué herramienta, qué argumentos, qué resultado, en cuánto tiempo. Listo para cumplimiento normativo por defecto.',
            ],
            'leads' => [
                'tab_name' => 'Leads',
                'title' => 'Captura leads durante la conversación',
                'description' => 'Deja que tu agente capture y califique leads en medio de una conversación, y luego los envíe a tu CRM a través de un servidor MCP o una función personalizada.',
            ],
        ],
    ],

    'steps' => [
        'title' => 'De la idea a un agente en producción en minutos',
        'create_title' => 'Crea',
        'create_desc' => 'Crea un agente con un clic — ponle nombre, elige un modelo y decide cómo quieres desplegarlo.',
        'train_title' => 'Entrena y conecta',
        'train_desc' => 'Entrénalo con tus datos — sube documentos, rastrea tu sitio y conecta servidores MCP y funciones personalizadas para que pueda actuar en tus herramientas.',
        'deploy_title' => 'Despliega',
        'deploy_desc' => 'Despliega en cualquier lugar — incrústalo, comparte un enlace hospedado o llámalo desde tu aplicación. Tu agente está en línea y funcionando con tus herramientas actuales.',
    ],

    'pricing' => [
        'heading' => 'Precios',
        'pay_monthly' => 'Pago mensual',
        'pay_yearly' => 'Pago anual',
        'per_month' => '/mes',
        'billed_yearly_at' => 'Facturado anualmente a',
        'custom' => 'Personalizado',
        'unlimited' => 'Ilimitado',
        'lets_talk' => 'Hablemos',
        'contact_sales' => 'Contactar ventas',
        'start_free_trial' => 'Comenzar prueba gratuita',
        'comparison_caption' => 'Comparación de planes',
        'price_sr' => 'Precio',
        'days' => 'días',
        'included_in' => 'Incluido en :plan',
        'not_included_in' => 'No incluido en :plan',
        'what_is' => '¿Qué es :label?',
        'groups' => [
            'usage' => 'Uso',
            'features' => 'Funciones',
            'security_support' => 'Seguridad y soporte',
        ],
        'rows' => [
            'messages' => 'Mensajes / mes',
            'agents' => 'Agentes de IA',
            'pages' => 'Páginas de conocimiento',
            'links' => 'Enlaces de sitio web',
            'minutes' => 'Minutos de audio / video',
            'team_members' => 'Miembros del equipo',
            'teams' => 'Equipos',
            'chat_history' => 'Historial de chat',
            'sentiment' => 'Análisis de sentimiento',
            'tone' => 'Análisis de tono',
            'topics' => 'Análisis de temas',
            'pulse' => 'Pulse de conversación',
            'handoff' => 'Handoff a humanos',
            'api' => 'Acceso a API',
            'mcp' => 'MCP',
            'function_calling' => 'Llamadas a funciones',
            'integrations' => 'Integraciones personalizadas',
            'white_label' => 'White-label (eliminar marca)',
            'sso' => 'SSO / SAML',
            'sla' => 'SLA de disponibilidad',
            'support' => 'Soporte dedicado',
            'dpa' => 'Contrato personalizado y DPA',
        ],
        'tips' => [
            'chat_history' => 'Cuánto tiempo se almacena cada conversación antes de ser eliminada automáticamente.',
            'sentiment' => 'Detecta automáticamente si cada conversación es positiva, neutral o negativa.',
            'tone' => 'Lee el tono emocional de un chat para que tus respuestas siempre estén alineadas con tu marca.',
            'topics' => 'Agrupa las conversaciones por tema para que puedas ver qué preguntan más las personas.',
            'pulse' => 'Una puntuación de salud en tiempo real para cada conversación que combina sentimiento y tono para señalar los chats que necesitan atención.',
            'handoff' => 'Permite que un agente humano intervenga y tome el control de la IA durante un chat en vivo.',
            'api' => 'Conecta Chatvia a tus propias aplicaciones y flujos de trabajo a través de nuestra API.',
            'mcp' => 'Conecta herramientas y datos externos a tu agente usando el Model Context Protocol.',
            'function_calling' => 'Permite que tu agente llame a tus propias funciones y webhooks para ejecutar acciones durante un chat.',
            'integrations' => 'Conecta tus propias herramientas y servicios para ampliar lo que tus agentes pueden hacer.',
            'white_label' => 'Oculta el badge "Powered by Chatvia" en tu widget y páginas de chat.',
            'sso' => 'Permite que tu equipo inicie sesión a través del proveedor de identidad de tu empresa, como Okta o Azure AD.',
            'sla' => 'Una garantía escrita sobre la disponibilidad del servicio.',
            'support' => 'Un contacto asignado y tiempos de respuesta más rápidos para tu equipo.',
            'dpa' => 'Un acuerdo a medida y términos de procesamiento de datos para tu equipo legal.',
        ],
    ],

    'faqs' => [
        'heading' => 'Preguntas frecuentes',
        'subtitle' => 'Todo lo que necesitas saber sobre la creación de agentes de IA con Chatvia.',
        'still_questions' => '¿Aún tienes preguntas?',
        'talk_to_ai' => 'Habla con nuestra IA',
        'items' => [
            'what' => [
                'question' => '¿Qué es Chatvia?',
                'answer' => 'Chatvia es una plataforma B2B para crear y entrenar tus propios agentes de IA con tus datos. Tus agentes responden desde tu base de conocimiento y actúan en tus herramientas — conecta servidores MCP, llama a tus APIs con funciones personalizadas y despliega en cualquier lugar.',
            ],
            'support_bot' => [
                'question' => '¿Chatvia es solo un chatbot de soporte?',
                'answer' => 'No. Chatvia sirve para crear agentes de IA para cualquier flujo de trabajo — asistentes de conocimiento interno, herramientas de investigación, consultas de datos, flujos de reservas y pedidos, y mucho más. El soporte al cliente es un caso de uso que puedes construir, no el producto completo.',
            ],
            'apis' => [
                'question' => '¿Mis agentes pueden llamar a nuestras APIs y herramientas?',
                'answer' => 'Sí. Define funciones personalizadas (webhooks) para que un agente pueda llamar a cualquier endpoint HTTP, y conecta servidores MCP para darle conjuntos completos de herramientas de una vez. El agente decide cuándo llamarlas durante una conversación para obtener datos o ejecutar acciones.',
            ],
            'mcp' => [
                'question' => '¿Qué es MCP y puedo conectar mis propios servidores?',
                'answer' => 'MCP (Model Context Protocol) es un estándar abierto para dar herramientas a los agentes. Puedes conectar cualquier servidor MCP remoto en segundos y elegir qué herramientas puede usar cada agente — incluidos tus propios servidores internos.',
            ],
            'training' => [
                'question' => '¿Qué significa entrenar?',
                'answer' => 'Entrenar procesa tus datos a través de un pipeline de recuperación (RAG) para que el agente los entienda y responda desde tu contenido real en lugar de adivinar. Nunca entrenamos modelos de terceros con tus datos, así que permanecen privados.',
            ],
            'sources' => [
                'question' => '¿Puedo entrenar con mi sitio web, documentos, audio y video?',
                'answer' => 'Sí. Sube documentos (PDF, Word, Texto), rastrea tu sitio web o conecta fuentes de datos — y el audio y video se transcriben automáticamente. Tu agente aprende de tu contenido y se mantiene actualizado cuando cambia.',
            ],
            'models' => [
                'question' => '¿Qué modelos de IA usa Chatvia?',
                'answer' => 'Puedes elegir el mejor modelo para cada agente y cambiarlo en cualquier momento.',
            ],
            'agent_count' => [
                'question' => '¿Cuántos agentes de IA puedo crear?',
                'answer' => 'Depende de tu plan. Consulta la sección de precios para ver el número de agentes incluidos en cada plan.',
            ],
            'white_label' => [
                'question' => '¿Puedo personalizar y hacer white-label de mis agentes?',
                'answer' => 'Sí. Personaliza el nombre, la persona, la marca y el logo de cada agente, y sírvelo en tu propio dominio para una experiencia completamente white-label.',
            ],
            'security' => [
                'question' => '¿Mis datos son seguros y privados?',
                'answer' => 'Chatvia es compatible con GDPR, está alojado en la UE y utiliza seguridad de nivel empresarial. Nunca entrenamos modelos de terceros con tus datos, y tus documentos y conversaciones permanecen privados y cifrados.',
            ],
            'team' => [
                'question' => '¿Ofrecen colaboración en equipo?',
                'answer' => 'Sí. Todos los planes incluyen colaboración en equipo — invita a miembros del equipo y comparte agentes con todo tu equipo.',
            ],
            'payment' => [
                'question' => '¿Qué métodos de pago aceptan?',
                'answer' => 'Aceptamos todas las tarjetas de crédito principales (Visa, MasterCard, American Express) y PayPal, procesados de forma segura a través de Stripe. Mejora, reduce o cancela tu plan en cualquier momento.',
            ],
        ],
    ],

    'cta' => [
        'headline_pre' => 'Lanza tu primer agente de IA en',
        'headline_highlight' => 'minutos',
        'description' => 'Crea agentes entrenados con tus datos que conectan servidores MCP, llaman a tus APIs y actúan en tus herramientas actuales — sin código, sin ingenieros.',
        'button' => 'Comienza tu prueba gratuita',
        'talk_to_ai' => 'Habla con nuestra IA',
    ],

    'footer' => [
        'terms' => 'Términos de servicio',
        'privacy' => 'Privacidad',
        'refund' => 'Política de reembolso',
        'subprocessors' => 'Sub-procesadores',
        'made_with_love' => 'Made with ❤️ in Denmark',
    ],

    'cookie_banner' => [
        'message' => 'Usamos cookies esenciales para que el sitio funcione. Sin cookies de seguimiento.',
        'accept' => 'Entendido',
        'privacy' => 'Política de privacidad',
    ],

];
