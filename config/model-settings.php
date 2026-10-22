<?php

declare(strict_types=1);

use Domain\Agent\Models\Agent;
use Domain\Llm\Enums\RealtimeVoice;

return [
    /*
    |--------------------------------------------------------------------------
    | Model Settings Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the behavior of the model settings system.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Database Configuration
    |--------------------------------------------------------------------------
    |
    | Configure database behavior for model settings.
    |
    */
    'database' => [
        'table' => env('MODEL_SETTINGS_TABLE', 'model_settings'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    |
    | Define default settings for specific models. These will be used as
    | fallback values when a setting doesn't exist in the database.
    |
    | The key should be the full model class name, and the value should be
    | an array of setting keys with their default values.
    |
    */
    'defaults' => [
        Agent::class => [
            // Shared content settings — apply across every surface (Hosted Chat,
            // Widget, Bar). Setup writes here once and all three surfaces read
            // from it.
            'appearance' => [
                'avatar' => null,
                'logo' => null,
                'color_theme' => 'black',
                'color_shade' => 700,
                'welcome_message' => 'What can I help with? ',
                'chat_window_title' => 'Chat',
                'composer_placeholder' => 'Ask anything...',
                // Optional link to the workspace's own privacy policy. When set,
                // the chat surfaces (widget, hosted chat, bar) show a "By
                // chatting, you agree to our privacy policy" consent notice that
                // links here. Empty = no notice. Shared content, so all surfaces
                // read the same value.
                'privacy_policy_url' => null,
                // Composer affordances. File uploads default off — most agents
                // don't need visitors attaching files, and owners can opt in
                // from Setup when the use case calls for it. Voice input stays
                // on by default.
                'allow_file_uploads' => false,
                'allow_voice_input' => true,
                // Live voice conversations (Hosted Chat and Chat Widget): a
                // button beside the mic opens a spoken call with the agent.
                // Off by default — realtime audio costs far more than text —
                // and only shown while voice is set up (chatvia.ai.voice).
                // `voice` is the agent's own voice, or `default` for the one
                // set in the admin panel (RealtimeVoice).
                'allow_voice_conversation' => false,
                'voice' => RealtimeVoice::DEFAULT,
                // The "N Sources" button under an answer (Hosted Chat, Chat
                // Widget, voice transcripts). Off by default: a list of
                // knowledge sources under small talk confuses chatters, so
                // owners opt in. Off means the sources never leave the server.
                'show_sources' => false,
                // Per-locale copies of the three text strings above
                // (chat_window_title / welcome_message / composer_placeholder).
                // The base values stay the source/default; these hold the
                // localized variants the chat surfaces resolve by visitor
                // language (AppearanceTextResolver).
                //   text_translations — AI output, regenerated whenever the
                //     owner saves changed base text. Shape: { field: { locale: text } }.
                //   text_overrides   — the owner's manual per-language edits.
                //     They win over the AI output and a re-translate never
                //     overwrites them.
                'text_translations' => [],
                'text_overrides' => [],
                // The owner's curated set of UI languages (2-letter codes, a
                // subset of chatvia.languages). Empty ⇒ "all" everywhere — the
                // hosted-chat switcher, the Setup per-language editors, and the
                // auto-translation targets are derived via Agent::enabledLanguages().
                'enabled_languages' => [],
            ],
            // Per-surface on/off switch. Each of the three web surfaces (Hosted
            // Chat, Widget, Command Bar) can be disabled independently from the
            // Deploy page. All default ON, so existing agents are unaffected.
            // Disabled Hosted Chat 404s (EnsureChatIsAccessible); disabled Widget
            // / Bar stop the embed from mounting (BuildWidgetSettingsAction exposes
            // the flags, the embedded widget honours them).
            'surfaces' => [
                'hosted_chat' => true,
                'widget' => true,
                'bar' => true,
            ],
            // Bar surface — surface-specific behaviour only (text/branding now
            // come from `appearance`). Single model lives on `agents.llm_id`.
            'bar' => [
                'trigger' => 'cmd+k',
                'placement' => 'top',
            ],
            // Widget surface — surface-specific behaviour only.
            'widget' => [
                'sites' => [],
                'position' => 'bottom-right',
                'window_size' => 'large',
                'theme' => 'auto',
                'theme_color' => 'black',
                'theme_shade' => 700,
                'show_on_load' => false,
                'show_on_load_delay' => 500,
                'bubbles' => [],
                'language' => 'auto',
                'handoff_enabled' => true,
            ],
            'embed' => [
                'remove_sidebar' => false,
                'hide_sidebar_on_page_load' => false,
                'enable_theme_switcher' => true,
                'default_theme' => 'auto',
                'enable_chat_expand' => true,
                'language' => 'auto',
                // Visitor-facing language picker in the hosted/embedded chat
                // header. On by default so visitors can read the chat (and its
                // translated title/welcome/placeholder) in their own language.
                'show_language_switcher' => true,
            ],
            // LLM hyperparameters — shared by every surface, since each agent
            // now has a single LLM (`agents.llm_id`).
            'llm_settings' => [
                'temperature' => 1.0,
                'top_k' => 0,
                'top_p' => 1.0,
                'presence_penalty' => 0.0,
                'frequency_penalty' => 0.0,
            ],
            // How hard the agent's model thinks before it answers
            // (ReasoningEffort). Saved with the model (UpdateAgentLlmAction),
            // used only while the admin lets agents reason.
            'reasoning' => [
                'effort' => 'off',
            ],
            // Per-agent data & insight switches (Setup → Settings).
            //  - chatlog: master switch for persisting conversations, on by
            //    default. Off stops StoreMessageJob, so nothing is logged — and
            //    the insights below get no data to work with.
            //  - sentiment/tone/topics: the optional LLM-powered insights,
            //    default off so owners opt in to the extra analysis. Each also
            //    gates on the plan-tier capability — a dimension runs only when
            //    the plan allows it AND the agent has switched it on.
            'analytics' => [
                'chatlog' => true,
                'sentiment' => false,
                'tone' => false,
                'topics' => false,
            ],
            'leads' => [
                'fields' => [
                    'name' => ['title' => 'Name', 'required' => true],
                    'email' => ['title' => 'Email', 'required' => true],
                    'phone' => ['title' => 'Phone Number', 'required' => false],
                    'job_title' => ['title' => 'Job Title', 'required' => false],
                    'company' => ['title' => 'Company', 'required' => false],
                    'location' => ['title' => 'Location', 'required' => false],
                    'message' => ['title' => 'Message', 'required' => false],
                ],
                'success' => [
                    'message' => 'Your information has been received and someone will get back to you soon.',
                ],
                // Optional per-agent CRM webhook. When enabled, every captured
                // lead (conversational tool OR inline form) is delivered as an
                // HMAC-signed JSON POST. SSRF-guarded at save and at dispatch.
                'crm' => [
                    'webhook_url' => null,
                    'webhook_secret' => null,
                    'webhook_enabled' => false,
                ],
            ],
            'business' => [
                'email' => null,
                'phone' => null,
                'website' => null,
                'address' => null,
                'opening_hours' => null,
            ],
            // Per-agent AI-safety guards (Guardrails page). On by default, so a
            // new agent is protected from the start. PII redaction is regex (the
            // filter list defaults to PiiFilterType::defaults()); injection,
            // toxicity and topics use one open-weight classifier call.
            'guardrails' => [
                'enabled' => true,
                'pii_actions' => [],      // filter key => mask|block; a missing key masks
                'custom_patterns' => [],  // owner patterns: {name, pattern, action}
                'allowlist' => [],        // values the PII filters never mask (besides the business contacts)
                'injection' => true,      // injection in messages, knowledge, tool results; prompt leaks in answers
                'injection_mode' => 'block',       // block | monitor (log only)
                'injection_sensitivity' => 'high', // block from a low-risk finding up
                'toxicity' => true,       // toxic messages and answers
                'toxicity_mode' => 'block',
                'toxicity_sensitivity' => 'medium', // block from a medium finding up
                'blocked_topics' => [],   // {name, description, examples, action, reply, reply_translations}
                'allowed_scope' => '',    // optional: only answer within this described scope
                'topics_mode' => 'block',
                'refusal_message' => '',  // empty = the generic refusal, from the language files
                'refusal_translations' => [], // AI translations of refusal_message, locale => text
                'check_before_showing' => false, // hold the answer until the output guards passed
                'alert_owner' => false,   // email the owner about blocked attacks and skipped checks
            ],
            // Per-agent chat throttle (Security page). Caps how many messages the
            // agent will answer per minute (aggregate across all visitors) before
            // returning a rate-limit error; owners adjust it to fit their traffic.
            // Optionally email the workspace owner the first time it trips.
            'security' => [
                'chat_rate_limit_per_minute' => 20,
                // Cap per visitor (one chat session or channel chat) per minute,
                // so one person cannot use up the agent's cap for everyone.
                'visitor_rate_limit_per_minute' => 8,
                'notify_owner_on_rate_limit' => false,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    |
    | Define validation rules for specific setting keys to ensure data integrity.
    | These rules can be used when validating settings before saving them.
    |
    */
    'validation' => [
        'appearance' => [
            'avatar' => ['nullable', 'image'],
            'logo' => ['nullable', 'image'],
            'color_theme' => ['nullable', 'string'],
            'color_shade' => ['nullable', 'integer', 'min:50', 'max:950'],
            'welcome_message' => ['nullable', 'string'],
            'chat_window_title' => ['nullable', 'string'],
            'composer_placeholder' => ['nullable', 'string'],
            'privacy_policy_url' => ['nullable', 'url', 'max:2048'],
            'allow_file_uploads' => ['nullable', 'boolean'],
            'allow_voice_input' => ['nullable', 'boolean'],
            'allow_voice_conversation' => ['nullable', 'boolean'],
            'voice' => ['nullable', 'string', 'in:'.implode(',', RealtimeVoice::agentChoices())],
            'show_sources' => ['nullable', 'boolean'],
            'text_translations' => ['nullable', 'array'],
            'text_overrides' => ['nullable', 'array'],
            'enabled_languages' => ['nullable', 'array'],
            'enabled_languages.*' => ['string', 'max:8'],
        ],
        'surfaces' => [
            'hosted_chat' => ['nullable', 'boolean'],
            'widget' => ['nullable', 'boolean'],
            'bar' => ['nullable', 'boolean'],
        ],
        'widget' => [
            'sites' => ['nullable', 'array'],
            'theme' => ['nullable', 'in:auto,light,dark'],
            'theme_color' => ['nullable', 'string'],
            'theme_shade' => ['nullable', 'integer', 'min:50', 'max:950'],
            'position' => ['nullable', 'in:bottom-right,bottom-left'],
            'window_size' => ['nullable', 'in:small,medium,large'],
            'primary_color' => ['nullable', 'string'],
            'show_theme_switcher' => ['nullable', 'boolean'],
            'show_on_load' => ['nullable', 'boolean'],
            'show_on_load_delay' => ['nullable', 'integer'],
            'language' => ['nullable', 'string'],
            'bubbles' => ['nullable', 'array'],
            'handoff_enabled' => ['nullable', 'boolean'],
        ],
        'embed' => [
            'remove_sidebar' => ['nullable', 'boolean'],
            'hide_sidebar_on_page_load' => ['nullable', 'boolean'],
            'enable_theme_switcher' => ['nullable', 'boolean'],
            'default_theme' => ['nullable', 'in:auto,dark,light'],
            'enable_chat_expand' => ['nullable', 'boolean'],
            'language' => ['nullable', 'string'],
            'show_language_switcher' => ['nullable', 'boolean'],
        ],
        'business' => [
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'opening_hours' => ['nullable', 'string', 'max:1000'],
        ],
        'bar' => [
            'trigger' => ['nullable', 'in:cmd+k,cmd+j,cmd+/,/,shift+/'],
            'placement' => ['nullable', 'in:top,top-right,bottom,bottom-right'],
        ],
        'llm_settings' => [
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'top_k' => ['nullable', 'integer', 'min:0', 'max:100'],
            'top_p' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'presence_penalty' => ['nullable', 'numeric', 'min:-2', 'max:2'],
            'frequency_penalty' => ['nullable', 'numeric', 'min:-2', 'max:2'],
        ],
        'analytics' => [
            'chatlog' => ['nullable', 'boolean'],
            'sentiment' => ['nullable', 'boolean'],
            'tone' => ['nullable', 'boolean'],
            'topics' => ['nullable', 'boolean'],
        ],
        'security' => [
            'chat_rate_limit_per_minute' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'notify_owner_on_rate_limit' => ['nullable', 'boolean'],
        ],
    ],
];
