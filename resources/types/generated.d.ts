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
        }
    }
    namespace Identity {
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
    }
}
declare namespace Domain {
    namespace Agent {
        namespace Data {
            namespace Resources {
                namespace Account {
                    export type AgentResourceData = {
                        id: number;
                        external_id: string;
                        name: string;
                        sub_domain: string | null;
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
                            sentiment: string | null;
                            tone: string | null;
                        };
                        export type ChatLogConversationsResourceData = {
                            conversations: Illuminate.LengthAwarePaginatorInterface<
                                number,
                                Domain.Agent.Data.Resources.ChatLog.Conversations.ChatLogConversationData
                            >;
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
                            sentiment: string | null;
                            tone: string | null;
                            feedback: string | null;
                            is_response_from_qa: boolean | null;
                            response_time_ms: number | null;
                            prompt_tokens: number | null;
                            completion_tokens: number | null;
                            llm_name: string | null;
                            llm_icon: string | null;
                            created_at: string;
                            agent_question_answer_id: number | null;
                            artifacts:
                                | {
                                      kind: string;
                                      data: any;
                                  }[]
                                | null;
                            source: string | null;
                            read_at: string | null;
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
                            sentiment: string | null;
                            tone: string | null;
                            total_messages: number;
                            user_messages: number;
                        };
                        export type ChatLogPulseMessageData = {
                            index: number;
                            content_preview: string;
                            tone: string | null;
                            sentiment: string | null;
                            sentiment_score: number;
                            tone_score: number;
                            created_at: string;
                        };
                        export type ChatLogPulsePeakData = {
                            index: number;
                            tone: string | null;
                            sentiment: string | null;
                        };
                        export type ChatLogPulseResourceData = {
                            messages: Domain.Agent.Data.Resources.ChatLog.Pulse.ChatLogPulseMessageData[];
                            summary: Domain.Agent.Data.Resources.ChatLog.Pulse.ChatLogPulseSummaryData;
                            conversation: Domain.Agent.Data.Resources.ChatLog.Pulse.ChatLogPulseConversationData;
                        };
                        export type ChatLogPulseSnapshotData = {
                            tone: string | null;
                            sentiment: string | null;
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
                    export type AgentSourcesStatsData = {
                        websites: number;
                        texts: number;
                        files: number;
                        markdown: number;
                        audio: number;
                        video: number;
                        youtube: number;
                    };
                    export type SourceFileDataResource = {
                        id: number;
                        name: string;
                        type: string;
                        size_in_kb: number;
                        mime_type: string;
                        status: string;
                        length: number;
                        progress: number;
                        agents_count: number;
                        created_at: string;
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
                    filetype: string;
                    is_audio_file: boolean;
                    is_video_file: boolean;
                };
            }
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
    namespace Conversation {
        namespace Enums {
            export type ConversationSource =
                'hosted_chat' | 'widget' | 'bar' | 'telegram' | 'slack' | 'messenger' | 'whatsapp' | 'msteams' | 'instagram';
            export type HandoffStatus = 'ai' | 'pending' | 'human' | 'returned';
            export type MessageRole = 'user' | 'assistant' | 'agent';
        }
        namespace Topic {
            namespace Data {
                export type TopicCountByDateData = {
                    date: string;
                    count: number;
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
                    scorer_type: string;
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
                    scorer_type: string;
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
            }
        }
        namespace Enums {
            export type EvalRunStatus = 'running' | 'completed' | 'failed';
        }
    }
    namespace Form {
        namespace Data {
            export type FormDefinitionData = {
                external_id: string | null;
                slug: string | null;
                name: string;
                subtitle: string | null;
                form_type: string;
                schema: Record<string, any>[];
                lead_field_map: Record<string, string> | null;
                success_message: string | null;
                agent_instructions: string | null;
                is_active: boolean;
                submissions_count: number;
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
                        authType: string | null;
                        tokenLabel: string | null;
                        tokenHelpUrl: string | null;
                        tokenPattern: string | null;
                        tokenPlaceholder: string | null;
                        requiresUrl: boolean;
                        comingSoon: boolean;
                        isCapability: boolean;
                        surfaces: string[] | null;
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
                        webhook_method: string;
                        webhook_header_names: string[];
                        has_webhook_headers: boolean;
                        auth_type: string;
                        timeout_ms: number;
                        max_response_bytes: number;
                        is_enabled: boolean;
                        last_invoked_at: string | null;
                        last_error: string | null;
                        invocation_count: number;
                        created_at: string;
                    };
                    export type AgentFunctionRowData = {
                        id: string;
                        external_id: string;
                        internal_name: string;
                        display_name: string | null;
                        description: string | null;
                        webhook_url: string;
                        webhook_method: string;
                        is_enabled: boolean;
                        last_error: string | null;
                        is_attached: boolean;
                    };
                    export type AgentFunctionsListResourceData = {
                        data: Domain.Integration.Function.Data.Resources.AgentFunctionRowData[];
                    };
                }
            }
        }
        namespace Mcp {
            namespace Data {
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
                        transport: string;
                        auth_type: string;
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
                export type McpToolPermission = 'allow' | 'approval' | 'blocked';
                export type McpToolScope = 'read' | 'write';
            }
        }
    }
    namespace Knowledge {
        namespace Source {
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
                id: string;
                internal_id: string;
                name: string;
                description: string;
                icon: string;
                context_window: number;
                features: Record<string, boolean>;
                location: string;
                is_reasoning: boolean;
                is_selected: boolean;
            };
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
    namespace Security {
        namespace Data {
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
                    handoff_status: string;
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
                    privacy_policy_url: string | null;
                    handoff_enabled: boolean;
                    allow_file_uploads: boolean;
                    allow_voice_input: boolean;
                    max_files_per_chat: number;
                };
            }
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
