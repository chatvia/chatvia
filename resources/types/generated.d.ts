declare namespace App {
    namespace Http {
        namespace Resources {
            namespace Handoff {
                export type HandoffMarkReadResultData = {
                    marked_count: number;
                };
                export type HandoffMessageResultData = {
                    success: boolean;
                    message: App.Http.Resources.Handoff.HandoffMessageResultPayloadData;
                };
                export type HandoffMessageResultPayloadData = {
                    id: string;
                    content: string;
                    role: Domain.Conversation.Enums.MessageRole;
                    created_at: string;
                };
                export type HandoffReturnResultData = {
                    success: boolean;
                    message: string;
                    handoff_status: Domain.Conversation.Enums.HandoffStatus;
                };
                export type HandoffSummaryData = {
                    conversation_id: string;
                    user_name: string;
                    user_email: string | null;
                    last_message: string | null;
                    last_message_at: string | null;
                    requested_at: string;
                    session_id: string;
                };
                export type HandoffTakeoverResultData = {
                    success: boolean;
                    message: string;
                    handoff_status: Domain.Conversation.Enums.HandoffStatus;
                    user_name: string | null;
                    user_email: string | null;
                };
                export type HandoffTypingResultData = {
                    success: boolean;
                };
                export type PendingHandoffsResourceData = {
                    pending: App.Http.Resources.Handoff.HandoffSummaryData[];
                    count: number;
                };
            }
            namespace Inbox {
                export type InboxAgentData = {
                    id: string;
                    name: string;
                };
                export type InboxConversationData = {
                    conversation_id: string;
                    agent: App.Http.Resources.Inbox.InboxAgentData;
                    visitor_name: string | null;
                    visitor_email: string | null;
                    country: string | null;
                    source: Domain.Conversation.Enums.ConversationSource;
                    handoff_status: Domain.Conversation.Enums.HandoffStatus;
                    held_by_me: boolean;
                    holder_name: string | null;
                    last_message: string | null;
                    last_message_role: Domain.Conversation.Enums.MessageRole | null;
                    last_activity_at: string | null;
                    is_online: boolean;
                    can_join: boolean;
                };
                export type InboxConversationsResourceData = {
                    conversations: App.Http.Resources.Inbox.InboxConversationData[];
                    waiting_count: number;
                };
                export type InboxWaitingCountData = {
                    count: number;
                };
            }
            namespace Notification {
                export type NotificationFeedResourceData = {
                    notifications: Domain.Notification.Data.UserNotificationData[];
                    next_cursor: string | null;
                    unread: number;
                };
            }
        }
    }
    namespace Identity {
        namespace ApiKey {
            namespace Data {
                export type ApiKeyRowData = {
                    id: number;
                    name: string;
                    abilities: string[] | null;
                    last_used_at: string | null;
                    created_at: string | null;
                };
            }
        }
        namespace Chatter {
            namespace Data {
                export type WorkspaceChatterRowData = {
                    external_id: string;
                    name: string;
                    email: string | null;
                    avatar: string | null;
                    status: string;
                    agents_count: number;
                    created_at: string;
                    messages_limit: number | null;
                    messages_used: number;
                    is_deactivated: boolean;
                    deactivation_reason: string | null;
                };
            }
        }
        namespace Workspace {
            namespace Data {
                namespace Account {
                    export type PasskeyRowData = {
                        id: number;
                        name: string;
                        last_used_at: string | null;
                        created_at: string | null;
                    };
                    export type SocialAccountRowData = {
                        provider: string;
                        label: string;
                        connected: boolean;
                        connected_at: string | null;
                    };
                }
                namespace DataExport {
                    export type DataExportResourceData = {
                        id: number;
                        status: string;
                        file_size: number | null;
                        expires_at: string | null;
                        error_message: string | null;
                        created_at: string | null;
                        download_url: string | null;
                    };
                }
            }
            namespace Enums {
                export type OnboardingTourStage = 'agent' | 'done';
            }
        }
    }
}
declare namespace Domain {
    namespace Agent {
        namespace Data {
            namespace ModelChoice {
                export type OfferedModelData = {
                    id: number;
                    name: string;
                    logo: string | null;
                    is_default: boolean;
                };
            }
            namespace Resources {
                namespace Account {
                    export type AgentResourceData = {
                        id: number;
                        external_id: string;
                        name: string;
                        slug: string | null;
                        domain: string | null;
                        system_instructions: string | null;
                        prompt_id: string | null;
                        is_public: boolean;
                        url: string;
                        permanent_url: string;
                        chat_url: string;
                        chat_embed_url: string;
                        created_at: string;
                        updated_at: string;
                        total_messages: number | null;
                        total_members: number | null;
                        forms_enabled: boolean;
                    };
                }
                namespace Chat {
                    export type ChatAgentRefData = {
                        id: string;
                        name: string;
                        slug: string | null;
                    };
                }
                namespace ChatLog {
                    namespace Conversations {
                        export type ChatLogConversationData = {
                            conversation_id: string;
                            last_at: string;
                            expires_at: string | null;
                            messages_count: number;
                            first_message_excerpt: string | null;
                            title: string | null;
                            source: Domain.Conversation.Enums.ConversationSource;
                            country: string | null;
                            country_name: string | null;
                            user_agent: string | null;
                            device_type: string | null;
                            browser: string | null;
                            os: string | null;
                            city: string | null;
                            referrer_url: string | null;
                            page_url: string | null;
                            locale: string | null;
                            topics: string[];
                            sentiment: Domain.Analytics.Enums.Sentiment | null;
                            tone: Domain.Analytics.Enums.Tone | null;
                        };
                        export type ChatLogConversationsResourceData = {
                            conversations: Domain.Agent.Data.Resources.ChatLog.Conversations.ChatLogConversationData[];
                            next_cursor: string | null;
                            lead_conversation_ids: number[];
                        };
                    }
                    namespace Countries {
                        export type ChatLogCountriesResourceData = {
                            countries: Domain.Agent.Data.Resources.ChatLog.Countries.ChatLogCountryData[];
                        };
                        export type ChatLogCountryData = {
                            id: number;
                            code: string;
                            name: string;
                        };
                    }
                    namespace Handoff {
                        export type ChatLogHandoffStatusData = {
                            status: string;
                            admin_id: number | null;
                            admin_name: string | null;
                            handoff_at: string | null;
                        };
                        export type ChatLogLeadData = {
                            id: number;
                            name: string | null;
                            email: string | null;
                            phone: string | null;
                            job_title: string | null;
                            company: string | null;
                            location: string | null;
                            message: string | null;
                            referrer_url: string | null;
                            page_url: string | null;
                            locale: string | null;
                            created_at: string;
                        };
                    }
                    namespace Messages {
                        export type ChatLogMessageData = {
                            id: string;
                            chat_conversation_id: string;
                            content: string;
                            role: string;
                            sentiment: Domain.Analytics.Enums.Sentiment | null;
                            tone: Domain.Analytics.Enums.Tone | null;
                            feedback: string | null;
                            feedback_comment: string | null;
                            is_response_from_qa: boolean;
                            not_found: boolean;
                            response_time_ms: number | null;
                            prompt_tokens: number | null;
                            completion_tokens: number | null;
                            llm_name: string | null;
                            created_at: string;
                            agent_question_answer_id: number | null;
                            artifacts:
                                | {
                                      kind: string;
                                      data: any;
                                  }[]
                                | null;
                            source: Domain.Conversation.Enums.MessageOrigin | null;
                            read_at: string | null;
                            confidence_score: number | null;
                            voice: boolean;
                            trace_id: string | null;
                            sources: Domain.Agent.Data.Resources.ChatLog.Messages.ChatLogMessageSourceData[];
                        };
                        export type ChatLogMessageSourceData = {
                            id: string | null;
                            label: string;
                            kind: Domain.Knowledge.Retrieval.Enums.SourceKind;
                            url: string | null;
                            page: number | null;
                            passages: number;
                            rank: number;
                            blocks: number[];
                            score: number | null;
                            preview: string | null;
                        };
                        export type ChatLogMessagesResourceData = {
                            messages: Illuminate.LengthAwarePaginator<number, Domain.Agent.Data.Resources.ChatLog.Messages.ChatLogMessageData>;
                            conversation_title: string | null;
                            lead: Domain.Agent.Data.Resources.ChatLog.Handoff.ChatLogLeadData | null;
                            handoff: Domain.Agent.Data.Resources.ChatLog.Handoff.ChatLogHandoffStatusData;
                        };
                    }
                    namespace Pulse {
                        export type ChatLogPulseConversationData = {
                            id: string;
                            sentiment: Domain.Analytics.Enums.Sentiment | null;
                            tone: Domain.Analytics.Enums.Tone | null;
                            total_messages: number;
                            user_messages: number;
                        };
                        export type ChatLogPulseMessageData = {
                            index: number;
                            content_preview: string;
                            tone: Domain.Analytics.Enums.Tone | null;
                            sentiment: Domain.Analytics.Enums.Sentiment | null;
                            sentiment_score: number;
                            tone_score: number;
                            created_at: string;
                        };
                        export type ChatLogPulsePeakData = {
                            index: number;
                            tone: Domain.Analytics.Enums.Tone | null;
                            sentiment: Domain.Analytics.Enums.Sentiment | null;
                        };
                        export type ChatLogPulseResourceData = {
                            messages: Domain.Agent.Data.Resources.ChatLog.Pulse.ChatLogPulseMessageData[];
                            summary: Domain.Agent.Data.Resources.ChatLog.Pulse.ChatLogPulseSummaryData;
                            conversation: Domain.Agent.Data.Resources.ChatLog.Pulse.ChatLogPulseConversationData;
                        };
                        export type ChatLogPulseSnapshotData = {
                            tone: Domain.Analytics.Enums.Tone | null;
                            sentiment: Domain.Analytics.Enums.Sentiment | null;
                        };
                        export type ChatLogPulseSummaryData = {
                            started_as: Domain.Agent.Data.Resources.ChatLog.Pulse.ChatLogPulseSnapshotData | null;
                            ended_as: Domain.Agent.Data.Resources.ChatLog.Pulse.ChatLogPulseSnapshotData | null;
                            peak_negative: Domain.Agent.Data.Resources.ChatLog.Pulse.ChatLogPulsePeakData | null;
                            peak_positive: Domain.Agent.Data.Resources.ChatLog.Pulse.ChatLogPulsePeakData | null;
                            avg_sentiment: number;
                            trend: string;
                            resolution: boolean;
                        };
                    }
                    namespace Topics {
                        export type ChatLogTopicData = {
                            id: number;
                            topic: string;
                            description: string | null;
                        };
                        export type ChatLogTopicsResourceData = {
                            topics: Domain.Agent.Data.Resources.ChatLog.Topics.ChatLogTopicData[];
                        };
                    }
                }
                namespace Sources {
                    export type AgentSourcesListData = {
                        id: number;
                        filename: string;
                        filetype: Domain.Knowledge.Source.File.Enums.KnowledgeFileType;
                        length: string;
                        agents: {
                            id: number;
                            name: string;
                        }[];
                    };
                    export type AgentSourcesStatsData = {
                        websites: number;
                        texts: number;
                        files: number;
                        markdown: number;
                        audio: number;
                        video: number;
                        youtube: number;
                    };
                    export type SourceFileResourceData = {
                        id: number;
                        name: string;
                        type: Domain.Knowledge.Source.File.Enums.KnowledgeFileType;
                        size_in_kb: number;
                        mime_type: string;
                        status: Domain.Knowledge.Source.Enums.SourceStatus;
                        length: number;
                        progress: number;
                        agents_count: number;
                        created_at: string;
                        personal_data: string[];
                        personal_data_parts: number;
                    };
                }
                namespace Stats {
                    export type ConversationTrendData = {
                        date: string;
                        conversations: number;
                    };
                    export type CountryUsageData = {
                        country_code: string;
                        country_name: string;
                        count: number;
                        percentage: number;
                    };
                    export type DailyMessagesData = {
                        date: string;
                        messages: number;
                    };
                    export type DayOfWeekActivityData = {
                        day: string;
                        day_number: number;
                        count: number;
                    };
                    export type FeedbackData = {
                        positive: number;
                        negative: number;
                        total: number;
                    };
                    export type HourlyActivityData = {
                        hour: number;
                        count: number;
                    };
                    export type MessageByDayData = {
                        day: string;
                        date: string;
                        value: number;
                    };
                    export type StatsResourceData = {
                        total_messages: number;
                        total_conversations: number;
                        total_user_messages: number;
                        unique_sessions: number;
                        avg_response_time: number | null;
                        avg_first_response_time: number | null;
                        avg_messages_per_conversation: number | null;
                        avg_conversation_duration: number | null;
                        avg_tokens_per_message: number | null;
                        total_tokens_used: number | null;
                        peak_activity_hour: number | null;
                        conversations_with_feedback: number;
                        feedback: Domain.Agent.Data.Resources.Stats.FeedbackData;
                        messages_last_week: Domain.Agent.Data.Resources.Stats.MessageByDayData[];
                        country_usage: Domain.Agent.Data.Resources.Stats.CountryUsageData[];
                        daily_messages: Domain.Agent.Data.Resources.Stats.DailyMessagesData[];
                        conversations_trend: Domain.Agent.Data.Resources.Stats.ConversationTrendData[];
                        activity_by_hour: Domain.Agent.Data.Resources.Stats.HourlyActivityData[];
                        activity_by_day_of_week: Domain.Agent.Data.Resources.Stats.DayOfWeekActivityData[];
                        top_knowledge: Domain.Agent.Data.Resources.Stats.TopKnowledgeData[];
                        first_data_date: string | null;
                        last_updated: string;
                    };
                    export type TopKnowledgeData = {
                        name: string;
                        type: string | null;
                        accessed: number;
                    };
                }
            }
            namespace Sources {
                export type KnowledgeAnalyzeResourceData = {
                    length: number;
                    mime_type: string;
                    filetype: Domain.Knowledge.Source.File.Enums.KnowledgeFileType;
                    is_audio_file: boolean;
                    is_video_file: boolean;
                };
            }
        }
        namespace Enums {
            export type SourcesDisplay = 'hidden' | 'list' | 'citations';
        }
        namespace Prompt {
            namespace Data {
                namespace Resources {
                    export type AgentPromptPickerData = {
                        id: string;
                        name: string;
                        prompt: string;
                    };
                    export type AgentPromptPickerListData = {
                        data: Domain.Agent.Prompt.Data.Resources.AgentPromptPickerData[];
                    };
                    export type AgentPromptRowData = {
                        id: string;
                        name: string;
                        prompt: string;
                        agents_count: number;
                        created_at: string | null;
                    };
                }
            }
        }
    }
    namespace Analytics {
        namespace Data {
            namespace Agent {
                export type DailyMessagePointData = {
                    date: string;
                    messages: number;
                };
                export type MessageChartResourceData = {
                    data: Domain.Analytics.Data.Agent.DailyMessagePointData[];
                    period: number;
                };
                namespace Usage {
                    export type FunctionUsageRowData = {
                        name: string | null;
                        description: string | null;
                        count: number;
                        last_used_at: string | null;
                    };
                    export type KnowledgeUsageRowData = {
                        name: string;
                        sub_kind: string;
                        mime_type: string | null;
                        count: number;
                        last_used_at: string | null;
                    };
                    export type McpToolUsageRowData = {
                        tool_name: string;
                        tool_description: string | null;
                        server_name: string | null;
                        server_slug: string;
                        connector_slug: string | null;
                        count: number;
                        last_used_at: string | null;
                    };
                    export type SourceUsageResourceData = {
                        knowledge: Domain.Analytics.Data.Agent.Usage.KnowledgeUsageRowData[];
                        functions: Domain.Analytics.Data.Agent.Usage.FunctionUsageRowData[];
                        mcp_tools: Domain.Analytics.Data.Agent.Usage.McpToolUsageRowData[];
                    };
                }
            }
        }
        namespace Enums {
            export type Sentiment = 'positive' | 'negative' | 'neutral';
            export type Tone = 'neutral' | 'frustrated' | 'urgent' | 'confused' | 'satisfied';
        }
    }
    namespace Channel {
        namespace Instagram {
            namespace Data {
                export type InstagramAccountPickData = {
                    ig_id: string;
                    ig_username: string;
                    page_name: string;
                };
                export type InstagramChannelCardData = {
                    available: boolean;
                    connected: boolean;
                    username: string | null;
                    enabled: boolean;
                    pick: Domain.Channel.Instagram.Data.InstagramAccountPickData[] | null;
                };
            }
        }
        namespace Messenger {
            namespace Data {
                export type MessengerChannelCardData = {
                    available: boolean;
                    connected: boolean;
                    page_name: string | null;
                    enabled: boolean;
                    pick: Domain.Channel.Messenger.Data.MessengerPagePickData[] | null;
                };
                export type MessengerPagePickData = {
                    id: string;
                    name: string;
                };
            }
        }
        namespace Slack {
            namespace Data {
                export type SlackChannelCardData = {
                    connected: boolean;
                    team_name: string | null;
                    webhook_url: string | null;
                    enabled: boolean;
                };
            }
        }
        namespace Teams {
            namespace Data {
                export type TeamsChannelCardData = {
                    connected: boolean;
                    app_id: string | null;
                    webhook_url: string | null;
                    enabled: boolean;
                };
            }
        }
        namespace Telegram {
            namespace Data {
                export type TelegramChannelCardData = {
                    connected: boolean;
                    bot_username: string | null;
                    enabled: boolean;
                    commands: Domain.Channel.Telegram.Data.TelegramCommandRowData[];
                };
                export type TelegramCommandRowData = {
                    command: string;
                    description: string;
                    type: string;
                    value: string;
                    builtin: boolean;
                };
            }
        }
        namespace Whatsapp {
            namespace Data {
                export type WhatsappChannelCardData = {
                    available: boolean;
                    connected: boolean;
                    display_phone_number: string | null;
                    verified_name: string | null;
                    enabled: boolean;
                    app_id: string | null;
                    config_id: string | null;
                    graph_version: string | null;
                };
            }
        }
    }
    namespace Conversation {
        namespace Data {
            namespace Audit {
                export type ToolCallAuditFiltersData = {
                    outcome: string | null;
                    connector: string | null;
                    agent_id: number | null;
                };
                export type ToolCallAuditRowData = {
                    id: number;
                    created_at: string | null;
                    outcome: Domain.Integration.Tool.Enums.ToolCallOutcome;
                    agent_id: number | null;
                    agent_name: string;
                    agent_external_id: string | null;
                    user_id: number | null;
                    user_name: string | null;
                    avatar: string | null;
                    trace_id: string;
                    tool_name: string;
                    tool_kind: Domain.Integration.Tool.Enums.ToolKind;
                    connector_slug: string | null;
                    connector_name: string | null;
                    is_error: boolean;
                    gated: boolean;
                    latency_ms: number | null;
                    arguments: Record<string, any> | null;
                    arguments_preview: string | null;
                    arguments_truncated: boolean;
                    result_text: string | null;
                    conversation_external_id: string | null;
                    decided_at: string | null;
                    decision_note: string | null;
                };
            }
            namespace Conversations {
                export type ConversationSearchHitData = {
                    id: string;
                    title: string;
                    timestamp: number;
                };
            }
            namespace Messages {
                export type RegeneratedTurnData = {
                    user_message_content: string;
                    created_at: string;
                };
            }
        }
        namespace Enums {
            export type ConversationSource =
                'hosted_chat' | 'widget' | 'bar' | 'telegram' | 'slack' | 'messenger' | 'whatsapp' | 'msteams' | 'instagram' | 'api';
            export type HandoffStatus = 'ai' | 'pending' | 'human' | 'returned';
            export type MessageOrigin =
                | 'hosted_chat'
                | 'widget'
                | 'bar'
                | 'telegram'
                | 'slack'
                | 'messenger'
                | 'whatsapp'
                | 'msteams'
                | 'instagram'
                | 'api'
                | 'playground'
                | 'handoff';
            export type MessageRole = 'user' | 'assistant' | 'agent';
            export type UnansweredFilter = 'needs_look' | 'not_found';
        }
        namespace Topic {
            namespace Data {
                export type TopicCountByDateData = {
                    date: string;
                    count: number;
                };
                export type TopicRowData = {
                    id: number;
                    workspace_id: number | null;
                    topic: string;
                    description: string;
                    created_at: string | null;
                    updated_at: string | null;
                };
                export type TopicSummaryData = {
                    id: number;
                    name: string;
                    count: number;
                };
                export type TopicTrendData = {
                    topic_name: string;
                    counts: Domain.Conversation.Topic.Data.TopicCountByDateData[];
                };
                export type TopicsResourceData = {
                    topics_by_date: Domain.Conversation.Topic.Data.TopicTrendData[];
                    total_topics: Domain.Conversation.Topic.Data.TopicSummaryData[];
                    total_count: number;
                };
            }
        }
    }
    namespace Evaluation {
        namespace Data {
            namespace Resources {
                export type EvalCaseResourceData = {
                    id: number;
                    external_id: string;
                    name: string;
                    input: string;
                    scorer_type: Domain.Evaluation.Enums.ScorerType;
                    scorer_config: Record<string, any> | null;
                    expected_output: string | null;
                    active: boolean;
                    created_at: string;
                };
                export type EvalResultResourceData = {
                    id: number;
                    external_id: string;
                    case_id: number | null;
                    case_name: string;
                    scorer_type: Domain.Evaluation.Enums.ScorerType;
                    pass: boolean;
                    score: number | null;
                    actual_output: string | null;
                    actual_output_truncated: boolean;
                    sources_used: string[];
                    tools_called: string[];
                    judge_rationale: string | null;
                    latency_ms: number | null;
                    tokens: number | null;
                    trace_id: string | null;
                    created_at: string | null;
                };
                export type EvalRunResourceData = {
                    id: number;
                    external_id: string;
                    status: Domain.Evaluation.Enums.EvalRunStatus;
                    total_cases: number;
                    passed_count: number;
                    failed_count: number;
                    avg_score: number | null;
                    pass_rate: number | null;
                    total_tokens: number | null;
                    model_snapshot: string | null;
                    triggered_by_name: string | null;
                    triggered_by_avatar: string | null;
                    started_at: string | null;
                    finished_at: string | null;
                    created_at: string;
                };
                export type EvalSourceCoverageData = {
                    total: number;
                    tested: number;
                    untested: string[];
                };
            }
        }
        namespace Enums {
            export type EvalRunStatus = 'running' | 'completed' | 'failed';
            export type ScorerType = 'contains' | 'regex' | 'exact' | 'json_schema' | 'llm_judge' | 'tool_call' | 'citations';
        }
    }
    namespace Form {
        namespace Data {
            export type FormDefinitionData = {
                external_id: string | null;
                slug: string | null;
                name: string;
                subtitle: string | null;
                form_type: Domain.Form.Enums.FormType;
                schema: Record<string, any>[];
                lead_field_map: Record<string, string> | null;
                success_message: string | null;
                agent_instructions: string | null;
                is_active: boolean;
                submissions_count: number;
                managed_by: string | null;
            };
            export type FormPreviewAppearanceData = {
                title: string | null;
                logo_url: string | null;
                avatar_url: string | null;
                color_theme: string;
                color_shade: number;
                theme: string;
                composer_placeholder: string | null;
                voice_input: boolean;
                show_branding: boolean;
            };
            export type SubmissionData = {
                external_id: string;
                values: Record<string, any>;
                status: Domain.Form.Enums.FormSubmissionStatus;
                submitted_at: string | null;
                created_at: string;
                conversation_external_id: string | null;
            };
        }
        namespace Enums {
            export type FormSubmissionStatus = 'pending' | 'submitted' | 'expired';
            export type FormType = 'lead' | 'booking' | 'qualification' | 'generic';
        }
    }
    namespace Guardrail {
        namespace Data {
            export type GuardrailEventRowData = {
                id: number;
                external_id: string;
                created_at: string | null;
                agent_id: number | null;
                agent_name: string;
                agent_external_id: string | null;
                user_id: number | null;
                user_name: string | null;
                avatar: string | null;
                stage: Domain.Guardrail.Enums.GuardStage;
                guard_type: Domain.Guardrail.Enums.GuardType;
                guard_label: string;
                outcome: Domain.Guardrail.Enums.GuardOutcome;
                severity: Domain.Guardrail.Enums.GuardSeverity | null;
                detail: Record<string, any> | null;
                snippet: string | null;
                trace_id: string | null;
                conversation_external_id: string | null;
                visitor_key: string | null;
                offender: Domain.Guardrail.Data.GuardrailOffenderData | null;
            };
            export type GuardrailLogFiltersData = {
                guard_type: Domain.Guardrail.Enums.GuardType | null;
                outcome: Domain.Guardrail.Enums.GuardOutcome | null;
                visitor: string | null;
            };
            export type GuardrailOffenderData = {
                kind: Domain.Guardrail.Enums.GuardrailOffenderKind;
                blocked: boolean;
                recent_attempts: number;
                visitor_key: string | null;
                chatter_external_id: string | null;
            };
            export type GuardrailTestResultData = {
                blocked: boolean;
                text: string;
                reply: string | null;
                handoff: boolean;
                language: string | null;
                verdicts: Domain.Guardrail.Data.GuardrailTestVerdictData[];
            };
            export type GuardrailTestVerdictData = {
                guard_type: Domain.Guardrail.Enums.GuardType;
                stage: Domain.Guardrail.Enums.GuardStage;
                outcome: Domain.Guardrail.Enums.GuardOutcome;
                severity: Domain.Guardrail.Enums.GuardSeverity | null;
                detail: Record<string, any>;
            };
        }
        namespace Enums {
            export type GuardOutcome = 'blocked' | 'redacted' | 'flagged' | 'skipped';
            export type GuardSeverity = 'none' | 'low' | 'medium' | 'high';
            export type GuardStage = 'input' | 'source' | 'tool' | 'output';
            export type GuardType = 'prompt_injection' | 'pii' | 'blocked_topic' | 'toxicity' | 'prompt_leak';
            export type GuardrailOffenderKind = 'chatter' | 'visitor';
        }
    }
    namespace Integration {
        namespace Connector {
            namespace Data {
                namespace Account {
                    export type AccountConnectorRowData = {
                        kind: Domain.Integration.Connector.Enums.ConnectorRowKind;
                        connector: Domain.Integration.Connector.Data.Catalog.ConnectorData | null;
                        server: Domain.Integration.Mcp.Data.Resources.AgentMcpServerData | null;
                    };
                    export type AccountConnectorsResourceData = {
                        featured: Domain.Integration.Connector.Data.Account.AccountConnectorRowData[];
                        custom: Domain.Integration.Connector.Data.Account.AccountConnectorRowData[];
                    };
                }
                namespace Agent {
                    export type AgentCapabilityRowData = {
                        connector: Domain.Integration.Connector.Data.Catalog.ConnectorData;
                        enabled: boolean;
                        ready: boolean;
                        providers: Domain.Integration.Connector.Data.Agent.CapabilityProviderData[];
                        provider: string | null;
                    };
                    export type AgentConnectorAttachResultData = {
                        data: Domain.Integration.Mcp.Data.Resources.AgentMcpServerData;
                        is_attached: boolean;
                    };
                    export type AgentConnectorRowData = {
                        connector: Domain.Integration.Connector.Data.Catalog.ConnectorData;
                        state: Domain.Integration.Connector.Enums.ConnectorRowState;
                        server: Domain.Integration.Mcp.Data.Resources.AgentMcpServerData | null;
                        always_available: boolean;
                    };
                    export type AgentConnectorsResourceData = {
                        featured: Domain.Integration.Connector.Data.Agent.AgentConnectorRowData[];
                        custom: Domain.Integration.Connector.Data.Custom.AgentCustomConnectorRowData[];
                    };
                    export type CapabilityProviderData = {
                        connector: Domain.Integration.Connector.Data.Catalog.ConnectorData;
                        connected: boolean;
                    };
                }
                namespace Catalog {
                    export type ConnectorData = {
                        slug: string;
                        name: string;
                        description: string;
                        category: string;
                        icon: string;
                        brandColor: string;
                        authStrategy: Domain.Integration.Connector.Enums.ConnectorAuthStrategy;
                        mcpUrl: string;
                        priority: number;
                        provider: string;
                        logoUrl: string | null;
                        activityLabel: string | null;
                        authType: string | null;
                        tokenLabel: string | null;
                        tokenHelpUrl: string | null;
                        tokenPattern: string | null;
                        tokenPlaceholder: string | null;
                        requiresUrl: boolean;
                        comingSoon: boolean;
                        isCapability: boolean;
                        surfaces: string[] | null;
                        voice: boolean;
                        worksThrough: string[];
                        available: boolean;
                        needsLicense: boolean;
                        requiresClientId: boolean;
                        oauthClientId: string | null;
                        oauthAuthorizationEndpoint: string | null;
                        oauthTokenEndpoint: string | null;
                        oauthScopes: string | null;
                    };
                    export type ConnectorRefreshResultData = {
                        data: Domain.Integration.Mcp.Data.Resources.AgentMcpServerData;
                        tools_count: number;
                    };
                    export type ConnectorToolData = {
                        id: number;
                        name: string;
                        short_name: string;
                        description: string | null;
                        parameters: any;
                        scope: Domain.Integration.Mcp.Enums.McpToolScope;
                        permission: Domain.Integration.Mcp.Enums.McpToolPermission;
                    };
                    export type ConnectorToolsResourceData = {
                        tools: Domain.Integration.Connector.Data.Catalog.ConnectorToolData[];
                        cached_at: string | null;
                    };
                }
                namespace Custom {
                    export type AgentCustomConnectorRowData = {
                        external_id: string;
                        name: string;
                        slug: string;
                        url: string;
                        is_enabled: boolean;
                        catalog_tools_count: number;
                        is_attached: boolean;
                        always_available: boolean;
                    };
                }
            }
            namespace Enums {
                export type ConnectorAuthStrategy = 'none' | 'token' | 'oauth';
                export type ConnectorRowKind = 'featured' | 'custom';
                export type ConnectorRowState = 'attached' | 'unattached' | 'available';
            }
        }
        namespace Function {
            namespace Data {
                namespace Resources {
                    export type AgentFunctionAttachResultData = {
                        data: Domain.Integration.Function.Data.Resources.AgentFunctionData;
                        is_attached: boolean;
                    };
                    export type AgentFunctionData = {
                        external_id: string;
                        internal_name: string;
                        display_name: string | null;
                        description: string;
                        parameters_schema: Record<string, any>;
                        webhook_url: string;
                        webhook_method: Domain.Integration.Function.Enums.FunctionWebhookMethod;
                        webhook_header_names: string[];
                        has_webhook_headers: boolean;
                        auth_type: Domain.Integration.Function.Enums.FunctionAuthType;
                        timeout_ms: number;
                        max_response_bytes: number;
                        is_enabled: boolean;
                        last_invoked_at: string | null;
                        last_error: string | null;
                        invocation_count: number;
                        created_at: string;
                    };
                    export type AgentFunctionRowData = {
                        external_id: string;
                        internal_name: string;
                        display_name: string | null;
                        description: string | null;
                        webhook_url: string;
                        webhook_method: Domain.Integration.Function.Enums.FunctionWebhookMethod;
                        is_enabled: boolean;
                        last_error: string | null;
                        is_attached: boolean;
                    };
                    export type AgentFunctionsListResourceData = {
                        data: Domain.Integration.Function.Data.Resources.AgentFunctionRowData[];
                    };
                    export type WorkspaceFunctionRowData = {
                        id: string;
                        internal_name: string;
                        display_name: string | null;
                        description: string;
                        parameters_schema: string;
                        webhook_url: string;
                        webhook_method: Domain.Integration.Function.Enums.FunctionWebhookMethod;
                        auth_type: Domain.Integration.Function.Enums.FunctionAuthType;
                        auth_header_name: string;
                        has_auth_value: boolean;
                        is_enabled: boolean;
                        requires_approval: boolean;
                        created_at: string | null;
                        agents_count: number;
                    };
                }
            }
            namespace Enums {
                export type FunctionAuthType = 'none' | 'bearer' | 'header' | 'hmac';
                export type FunctionWebhookMethod = 'POST' | 'GET' | 'PATCH' | 'DELETE';
            }
        }
        namespace Mcp {
            namespace Data {
                export type ConnectorOptionData = {
                    slug: string;
                    name: string | null;
                };
                export type McpToolPermissionData = {
                    id: number;
                    permission: Domain.Integration.Mcp.Enums.McpToolPermission;
                };
                namespace Resources {
                    export type AgentMcpServerData = {
                        external_id: string;
                        name: string;
                        slug: string;
                        connector_slug: string | null;
                        description: string | null;
                        url: string;
                        transport: Domain.Integration.Mcp.Enums.McpTransport;
                        auth_type: Domain.Integration.Mcp.Enums.McpAuthType;
                        timeout_ms: number;
                        is_enabled: boolean;
                        catalog_tools_count: number;
                        catalog_cached_at: string | null;
                        last_invoked_at: string | null;
                        last_error: string | null;
                        invocation_count: number;
                        agents_count: number;
                    };
                }
            }
            namespace Enums {
                export type McpAuthType = 'none' | 'bearer' | 'header';
                export type McpToolPermission = 'allow' | 'approval' | 'blocked';
                export type McpToolScope = 'read' | 'write';
                export type McpTransport = 'streamable_http' | 'http_sse';
            }
        }
        namespace Tool {
            namespace Enums {
                export type ToolCallOutcome = 'auto' | 'approved' | 'denied' | 'rate_limited' | 'not_offered';
                export type ToolKind = 'mcp' | 'function';
            }
        }
    }
    namespace Knowledge {
        namespace Retrieval {
            namespace Data {
                export type KnowledgeSearchStateData = {
                    installEnabled: boolean;
                    canChange: boolean;
                    enabled: boolean;
                };
            }
            namespace Enums {
                export type SourceKind = 'file' | 'media' | 'website' | 'text' | 'video';
            }
        }
        namespace Source {
            namespace Enums {
                export type SourceStatus = 'uploaded' | 'pending' | 'crawling' | 'crawled' | 'ingesting' | 'failed' | 'ingested';
            }
            namespace File {
                namespace Enums {
                    export type KnowledgeFileType = 'pdf' | 'word' | 'spreadsheet' | 'presentation' | 'text' | 'ebook' | 'image' | 'audio' | 'video';
                }
            }
            namespace Qa {
                namespace Data {
                    export type QaQuestionItemData = {
                        id: string;
                        question: string;
                    };
                    export type QaRowData = {
                        id: number;
                        title: string;
                        questions: Domain.Knowledge.Source.Qa.Data.QaQuestionItemData[];
                        answer: string;
                        status: string;
                        created_at: string | null;
                        updated_at: string | null;
                    };
                }
            }
            namespace Text {
                namespace Data {
                    export type TrainedTextRowData = {
                        id: number;
                        excerpt: string;
                        word_count: number;
                        agents: Record<string, any>[];
                    };
                }
            }
        }
    }
    namespace Llm {
        namespace Data {
            export type LlmModelData = {
                id: number;
                name: string;
                provider: string;
                is_reasoning: boolean;
                is_selected: boolean;
                provider_icon: string | null;
                calls_tools: boolean;
                display_name: string | null;
                logo: string | null;
            };
        }
        namespace Enums {
            export type ReasoningEffort = 'off' | 'low' | 'medium' | 'high';
        }
    }
    namespace Membership {
        namespace Agent {
            namespace Data {
                namespace Resources {
                    export type AgentChatterRowData = {
                        external_id: string;
                        name: string;
                        email: string | null;
                        avatar: string | null;
                        status: string;
                        has_access: boolean;
                        created_at: string;
                        invitation_url: string | null;
                        invitation_expires_at: string | null;
                    };
                    export type AgentMembershipAssignmentData = {
                        attached: Domain.Membership.Agent.Data.Resources.AgentMembershipRowData[];
                        available: Domain.Membership.Agent.Data.Resources.AgentMembershipRowData[];
                    };
                    export type AgentMembershipRowData = {
                        id: string;
                        name: string;
                    };
                    export type ChatterInvitationLinkData = {
                        email: string;
                        accept_url: string;
                        expires_at: string;
                    };
                }
            }
        }
        namespace Team {
            namespace Data {
                namespace Resources {
                    namespace Members {
                        export type ManagedUserData = {
                            id: number;
                            name: string;
                            email: string;
                        };
                        export type TeamInvitationData = {
                            id: number;
                            email: string;
                            role: string | null;
                            role_name: string | null;
                            created_at: string;
                        };
                        export type TeamMemberData = {
                            id: number;
                            name: string;
                            email: string;
                            role: string;
                            role_name: string;
                            is_owner: boolean;
                            joined_at: string | null;
                        };
                    }
                    namespace Permissions {
                        export type PermissionGroupData = {
                            scope: string;
                            label: string;
                            description: string | null;
                            permissions: string[];
                        };
                        export type PermissionMetaData = {
                            label: string;
                            description: string;
                            requires: string[];
                        };
                        export type PermissionScopeData = {
                            label: string;
                            description: string;
                        };
                    }
                    namespace Roles {
                        export type RoleConfigData = {
                            name: string;
                            description: string | null;
                            permissions: string[];
                        };
                        export type TeamRoleData = {
                            id: number;
                            external_id: string;
                            code: string;
                            name: string;
                            description: string | null;
                            permissions: string[];
                            all_agents: boolean;
                            agent_ids: string[];
                        };
                    }
                    namespace Team {
                        export type TeamAgentData = {
                            id: number;
                            external_id: string;
                            name: string;
                        };
                        export type TeamData = {
                            id: number;
                            external_id: string;
                            name: string;
                            owner: Domain.Membership.Team.Data.Resources.Team.TeamOwnerData;
                        };
                        export type TeamListItemData = {
                            id: number;
                            external_id: string;
                            name: string;
                            owner: Domain.Membership.Team.Data.Resources.Team.TeamOwnerData;
                            is_owner: boolean;
                            is_primary: boolean;
                            role: string;
                            members_count: number;
                            agents_count: number;
                            created_at: string;
                        };
                        export type TeamOwnerData = {
                            id: number;
                            name: string;
                            email: string;
                        };
                    }
                }
            }
        }
    }
    namespace Notification {
        namespace Data {
            export type CategoryPreferenceData = {
                bell: boolean;
                desktop: boolean;
                sound: boolean;
            };
            export type NotificationAgentData = {
                id: string;
                name: string;
            };
            export type NotificationContentData = {
                url: string;
                agent: Domain.Notification.Data.NotificationAgentData | null;
                subject: string | null;
                detail: string | null;
                actor: string | null;
            };
            export type NotificationPreferencesData = {
                handoffs: Domain.Notification.Data.CategoryPreferenceData;
                security: Domain.Notification.Data.CategoryPreferenceData;
                training: Domain.Notification.Data.CategoryPreferenceData;
                leads: Domain.Notification.Data.CategoryPreferenceData;
                usage: Domain.Notification.Data.CategoryPreferenceData;
                reports: Domain.Notification.Data.CategoryPreferenceData;
            };
            export type NotificationSummaryData = {
                unread: number;
                preferences: Domain.Notification.Data.NotificationPreferencesData;
            };
            export type UserNotificationData = {
                id: string;
                kind: Domain.Notification.Enums.NotificationKind;
                category: Domain.Notification.Enums.NotificationCategory;
                content: Domain.Notification.Data.NotificationContentData;
                count: number;
                read: boolean;
                created_at: string;
                updated_at: string;
            };
        }
        namespace Enums {
            export type NotificationCategory = 'handoffs' | 'security' | 'training' | 'leads' | 'usage' | 'reports';
            export type NotificationFilter = 'all' | 'unread';
            export type NotificationKind =
                | 'handoff_requested'
                | 'source_trained'
                | 'source_training_failed'
                | 'lead_captured'
                | 'attack_blocked'
                | 'guardrail_skipped'
                | 'security_settings_changed'
                | 'new_device_sign_in'
                | 'message_limit_warning'
                | 'message_limit_reached'
                | 'rate_limit_exceeded'
                | 'eval_run_finished'
                | 'eval_regressed'
                | 'data_export_ready'
                | 'ai_model_unavailable';
        }
    }
    namespace Security {
        namespace Data {
            export type AgentRateLimitSettingsData = {
                chat_rate_limit_per_minute: number;
                notify_owner_on_rate_limit: boolean;
                visitor_rate_limit_per_minute: number;
            };
            export type BlockListResourceData = {
                visitors: Domain.Security.Data.BlockedVisitorRowData[];
                chatters: Domain.Security.Data.DeactivatedChatterRowData[];
            };
            export type BlockedVisitorRowData = {
                external_id: string;
                visitor: string;
                browser: string | null;
                os: string | null;
                country: string | null;
                agent_name: string | null;
                reason: string | null;
                blocked_by: string | null;
                created_at: string | null;
                expires_at: string | null;
            };
            export type DeactivatedChatterRowData = {
                external_id: string;
                name: string;
                reason: string | null;
                deactivated_at: string | null;
            };
            export type RetentionLimitsData = {
                conversations_max: number;
                conversations_floor: number;
                audit_default: number;
                audit_floor: number;
                leads_floor: number;
                analytics_floor: number;
                anonymize_floor: number;
                max_days: number;
            };
            export type SecurityAuditFiltersData = {
                category: string | null;
                outcome: string | null;
            };
            export type SecurityAuditRowData = {
                id: number;
                created_at: string | null;
                action: Domain.Security.Enums.SecurityAuditAction;
                action_label: string;
                category: string | null;
                outcome: Domain.Security.Enums.SecurityAuditOutcome;
                user_id: number | null;
                user_name: string | null;
                user_email: string | null;
                avatar: string | null;
                target_type: string | null;
                target_id: string | null;
                target_label: string | null;
                ip: string | null;
                user_agent: string | null;
                metadata: Record<string, any> | null;
                metadata_preview: string | null;
                metadata_truncated: boolean;
                integrity_verified: boolean;
            };
            export type WorkspaceSecurityPolicyData = {
                retention_conversations_days: number | null;
                retention_leads_days: number | null;
                retention_audit_days: number | null;
                retention_analytics_days: number | null;
                anonymize_after_days: number | null;
                ip_allowlist_mode: Domain.Security.Enums.IpAllowlistMode;
                ip_allowlist: string[];
                geo_mode: Domain.Security.Enums.GeoMode;
                geo_countries: string[];
                webhook_url: string | null;
                webhook_secret: string | null;
                webhook_enabled: boolean;
                webhook_events: string[] | null;
                scim_enabled: boolean;
            };
        }
        namespace Enums {
            export type GeoMode = 'off' | 'allow' | 'deny';
            export type IpAllowlistMode = 'off' | 'dashboard' | 'dashboard_api';
            export type SecurityAuditAction =
                | 'auth.login'
                | 'auth.login_failed'
                | 'auth.logout'
                | 'member.added'
                | 'member.removed'
                | 'member.role_changed'
                | 'role.created'
                | 'role.updated'
                | 'role.deleted'
                | 'agent.settings_changed'
                | 'api_key.created'
                | 'api_key.deleted'
                | 'connector.attached'
                | 'connector.detached'
                | 'policy.changed'
                | 'policy.ip_allowlist_changed'
                | 'policy.retention_changed'
                | 'policy.webhook_disabled'
                | 'data.exported'
                | 'data.deletion_requested'
                | 'audit.exported'
                | 'scim.user.provisioned'
                | 'scim.user.deprovisioned'
                | 'scim.group.changed'
                | 'guardrail.attack_blocked'
                | 'visitor.blocked'
                | 'visitor.unblocked'
                | 'chatter.blocked';
            export type SecurityAuditOutcome = 'success' | 'failure' | 'denied';
        }
    }
    namespace Widget {
        namespace Data {
            namespace Auth {
                export type WidgetAckData = {
                    success: boolean;
                };
                export type WidgetAgentVerifyResultData = {
                    verified: boolean;
                };
                export type WidgetAuthTokenData = {
                    auth_token: string;
                    expires_at: string;
                };
                export type WidgetBroadcastAuthData = {
                    auth: string;
                    channel_data: string;
                };
            }
            namespace Conversation {
                export type WidgetConversationStoredData = {
                    id: string;
                };
                export type WidgetConversationsResultData = {
                    conversations: any[];
                };
                export type WidgetMarkReadResultData = {
                    marked_count: number;
                };
            }
            namespace Handoff {
                export type WidgetHandoffRequestResultData = {
                    status: string;
                    conversation_id: string;
                    message: string;
                };
                export type WidgetHandoffStatusData = {
                    conversation_id: string;
                    handoff_status: Domain.Conversation.Enums.HandoffStatus;
                    is_human_controlled: boolean;
                    admin_name: string | null;
                };
            }
            namespace Session {
                export type WidgetEndChatResultData = {
                    success: boolean;
                    message: string;
                };
                export type WidgetSessionInitResultData = {
                    session_id: string;
                    conversation_id: string;
                    is_new_session: boolean;
                    is_new_conversation: boolean;
                };
            }
            namespace Settings {
                export type WidgetSettingsBrandingData = {
                    enabled: boolean;
                    name: string;
                    url: string;
                    logo_url: string;
                };
                export type WidgetSettingsData = {
                    protected: boolean;
                    widget_enabled: boolean;
                    bar_enabled: boolean;
                    title: string;
                    logo_url: string;
                    avatar_url: string | null;
                    welcome_message: string | null;
                    color_theme: string | null;
                    color_shade: string | null;
                    bubbles: any[];
                    bubble_translations: Record<string, string>[];
                    position: string | null;
                    show_on_load: boolean | null;
                    show_on_load_delay: number | null;
                    branding: Domain.Widget.Data.Settings.WidgetSettingsBrandingData;
                    chatvia_branding: boolean;
                    starters: any[];
                    text_translations: Record<string, Record<string, string>>;
                    window_size: string | null;
                    theme: string | null;
                    keyboard_trigger: string | null;
                    bar_placement: string | null;
                    composer_placeholder: string | null;
                    notice: string | null;
                    privacy_policy_url: string | null;
                    sources_display: Domain.Agent.Enums.SourcesDisplay;
                    handoff_enabled: boolean;
                    chat_deletion_mode: Domain.Widget.Enums.ChatDeletionMode;
                    ai_disclosure: boolean;
                    allow_file_uploads: boolean;
                    upload_accept: string[];
                    allow_voice_input: boolean;
                    voice_conversation: boolean;
                    feedback_comment: boolean;
                    max_files_per_chat: number;
                    reverb: Domain.Widget.Data.Settings.WidgetSettingsReverbData;
                    models: Domain.Agent.Data.ModelChoice.OfferedModelData[];
                };
                export type WidgetSettingsReverbData = {
                    key: string | null;
                    host: string | null;
                    port: number | null;
                    scheme: string | null;
                };
            }
        }
        namespace Enums {
            export type ChatDeletionMode = 'end' | 'hide' | 'delete';
        }
    }
}
declare namespace Illuminate {
    export type CursorPaginator<TKey, TValue> = {
        data: TKey extends string ? Record<TKey, TValue> : TValue[];
        links: {
            url: string | null;
            label: string;
            active: boolean;
        }[];
        meta: {
            path: string;
            per_page: number;
            next_cursor: string | null;
            next_page_url: string | null;
            prev_cursor: string | null;
            prev_page_url: string | null;
        };
    };
    export type CursorPaginatorInterface<TKey, TValue> = Illuminate.CursorPaginator<TKey, TValue>;
    export type LengthAwarePaginator<TKey, TValue> = {
        data: TKey extends string ? Record<TKey, TValue> : TValue[];
        links: {
            url: string | null;
            label: string;
            active: boolean;
        }[];
        meta: {
            total: number;
            current_page: number;
            first_page_url: string;
            from: number | null;
            last_page: number;
            last_page_url: string;
            next_page_url: string | null;
            path: string;
            per_page: number;
            prev_page_url: string | null;
            to: number | null;
        };
    };
    export type LengthAwarePaginatorInterface<TKey, TValue> = Illuminate.LengthAwarePaginator<TKey, TValue>;
}
