declare namespace App.Auth.Data.Resources.My.Usage {
    export type UsageResourceData = {
        subscription: App.Auth.Data.Resources.My.Usage.UsageSubscriptionData;
        agents: App.Auth.Data.Resources.My.Usage.UsageStatsData;
        pages: App.Auth.Data.Resources.My.Usage.UsageStatsData;
        minutes: App.Auth.Data.Resources.My.Usage.UsageStatsData;
        links: App.Auth.Data.Resources.My.Usage.UsageStatsData;
        messages: App.Auth.Data.Resources.My.Usage.UsageStatsData;
        teams: App.Auth.Data.Resources.My.Usage.UsageStatsData;
        members: App.Auth.Data.Resources.My.Usage.UsageStatsData;
    };
    export type UsageStatsData = {
        limit: number;
        used: number;
        remaining: number;
    };
    export type UsageSubscriptionData = {
        plan: string;
        started_at: any;
        ends_at: any;
    };
}
declare namespace App.Billing.Data.Resources {
    export type BillingCurrentPackageData = {
        id: string;
        name: string;
        description: string;
        features: App.Billing.Data.Resources.BillingPackageFeaturesData;
        monthly_price: number;
        monthly_price_formatted: string;
        yearly_price: number;
        yearly_price_formatted: string;
        is_yearly: boolean;
        is_popular: boolean;
        limits: App.Billing.Data.Resources.BillingQuotaLimitsData;
        capabilities: App.Billing.Data.Resources.BillingPackageCapabilitiesData;
    };
    export type BillingPackageCapabilitiesData = {
        sentiment: boolean;
        tone: boolean;
        topics: boolean;
        handoff: boolean;
        api: boolean;
        pulse: boolean;
    };
    export type BillingPackageFeaturesData = {
        monthly: { [key: number]: string } | Array<any>;
        yearly: { [key: number]: string } | Array<any>;
    };
    export type BillingPendingChangeData = {
        from_package: string | null;
        to_package: string | null;
        is_yearly: boolean | null;
        effective_date: number | null;
        scheduled_at: string | null;
        has_pending_update: boolean;
    };
    export type BillingQuotaFixedData = {
        agents_limit: number;
        pages_limit: number;
        minutes_limit: number;
        links_limit: number;
        custom_domains_limit: number;
        members_limit: number;
    };
    export type BillingQuotaLimitsData = {
        monthly: App.Billing.Data.Resources.BillingQuotaPeriodData;
        yearly: App.Billing.Data.Resources.BillingQuotaPeriodData;
        fixed: App.Billing.Data.Resources.BillingQuotaFixedData;
    };
    export type BillingQuotaPeriodData = {
        messages_limit: number;
    };
    export type BillingResourceData = {
        current_package: App.Billing.Data.Resources.BillingCurrentPackageData;
        on_trial: boolean;
        trial_ends_at: string | null;
        subscription: App.Billing.Data.Resources.BillingSubscriptionData | null;
        subscribed: boolean;
        subscription_canceled: boolean;
        pending_plan_change: App.Billing.Data.Resources.BillingPendingChangeData | null;
    };
    export type BillingSubscriptionData = {
        id: number;
        stripe_id: string;
        stripe_status: string;
        stripe_price: string | null;
        trial_ends_at: number | null;
        ends_at: number | null;
        current_period_end: number;
        current_period_start: number;
    };
}
declare namespace App.Teams.Data.Resources {
    export type ManagedUserData = {
        id: number;
        name: string;
        email: string;
    };
    export type PermissionGroupData = {
        scope: string;
        label: string;
        description: string | null;
        permissions: Array<string>;
    };
    export type PermissionMetaData = {
        label: string;
        description: string;
        requires: Array<string>;
    };
    export type PermissionScopeData = {
        label: string;
        description: string;
    };
    export type RoleConfigData = {
        name: string;
        description: string | null;
        permissions: Array<string>;
    };
    export type TeamAgentData = {
        id: number;
        external_id: string;
        name: string;
    };
    export type TeamData = {
        id: number;
        name: string;
        owner: App.Teams.Data.Resources.TeamOwnerData;
    };
    export type TeamInvitationData = {
        id: number;
        email: string;
        role: string | null;
        role_name: string | null;
        created_at: string;
    };
    export type TeamListItemData = {
        id: number;
        name: string;
        owner: App.Teams.Data.Resources.TeamOwnerData;
        is_owner: boolean;
        is_primary: boolean;
        role: string;
        members_count: number;
        agents_count: number;
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
    export type TeamOwnerData = {
        id: number;
        name: string;
        email: string;
    };
    export type TeamRoleData = {
        id: number;
        code: string;
        name: string;
        description: string | null;
        permissions: Array<string>;
        all_agents: boolean;
        agent_ids: Array<string>;
    };
}
declare namespace Domain.Agents.Data {
    export type KnowledgeAnalyzeResourceData = {
        length: number;
        mime_type: string;
        filetype: string;
        is_audio_file: boolean;
        is_video_file: boolean;
    };
}
declare namespace Domain.Agents.Data.Resources {
    export type AgentChatterRowData = {
        external_id: string;
        name: string;
        email: string | null;
        status: string;
        has_access: boolean;
    };
    export type AgentFunctionAttachResultData = {
        data: Domain.Agents.Data.Resources.AgentFunctionData;
        is_attached: boolean;
    };
    export type AgentFunctionData = {
        external_id: string;
        name: string;
        description: string;
        parameters_schema: { [key: string]: any };
        webhook_url: string;
        webhook_method: string;
        webhook_headers: { [key: number]: { [key: string]: string } };
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
        name: string;
        description: string | null;
        is_enabled: boolean;
        is_attached: boolean;
    };
    export type AgentFunctionsListResourceData = {
        data: { [key: number]: Domain.Agents.Data.Resources.AgentFunctionRowData };
    };
    export type AgentLlmDefaultSettingsData = {
        temperature: number;
        top_k: number;
        top_p: number;
        presence_penalty: number;
        frequency_penalty: number;
    };
    export type AgentLlmSettingsData = {
        temperature: number;
        top_k: number;
        top_p: number;
        presence_penalty: number;
        frequency_penalty: number;
    };
    export type AgentMcpServerData = {
        external_id: string;
        name: string;
        slug: string;
        connector_slug: string | null;
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
    export type AgentMembershipAssignmentData = {
        attached: { [key: number]: Domain.Agents.Data.Resources.AgentMembershipRowData };
        available: { [key: number]: Domain.Agents.Data.Resources.AgentMembershipRowData };
    };
    export type AgentMembershipRowData = {
        id: string;
        name: string;
    };
    export type AgentPromptPickerData = {
        id: string;
        name: string;
        prompt: string;
    };
    export type AgentPromptPickerListData = {
        data: { [key: number]: Domain.Agents.Data.Resources.AgentPromptPickerData };
    };
    export type AgentPromptRowData = {
        id: string;
        name: string;
        prompt: string;
        agents_count: number;
        created_at: string | null;
    };
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
        unique_users: number | null;
        total_members: number | null;
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
    export type LeadResourceData = {
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
        ip_address: string | null;
        user_agent: string | null;
        created_at: string;
        conversation_id: string | null;
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
declare namespace Domain.Agents.Data.Resources.ChatLog {
    export type ChatLogConversationData = {
        conversation_id: string;
        last_at: string;
        messages_count: number;
        first_message_excerpt: string | null;
        title: string | null;
        source: any;
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
        topic: string | null;
        topic_id: string | null;
        sentiment: string | null;
        tone: string | null;
    };
    export type ChatLogConversationsResourceData = {
        conversations: any;
        lead_conversation_ids: Array<any>;
    };
    export type ChatLogCountriesResourceData = {
        countries: { [key: number]: Domain.Agents.Data.Resources.ChatLog.ChatLogCountryData };
    };
    export type ChatLogCountryData = {
        id: number;
        code: string;
        name: string;
    };
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
        artifacts: Array<any> | null;
        source: string | null;
        read_at: string | null;
    };
    export type ChatLogMessagesResourceData = {
        messages: any;
        conversation_title: string | null;
        lead: Domain.Agents.Data.Resources.ChatLog.ChatLogLeadData | null;
        handoff: Domain.Agents.Data.Resources.ChatLog.ChatLogHandoffStatusData;
    };
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
        messages: { [key: number]: Domain.Agents.Data.Resources.ChatLog.ChatLogPulseMessageData };
        summary: Domain.Agents.Data.Resources.ChatLog.ChatLogPulseSummaryData;
        conversation: Domain.Agents.Data.Resources.ChatLog.ChatLogPulseConversationData;
    };
    export type ChatLogPulseSnapshotData = {
        tone: string | null;
        sentiment: string | null;
    };
    export type ChatLogPulseSummaryData = {
        started_as: Domain.Agents.Data.Resources.ChatLog.ChatLogPulseSnapshotData | null;
        ended_as: Domain.Agents.Data.Resources.ChatLog.ChatLogPulseSnapshotData | null;
        peak_negative: Domain.Agents.Data.Resources.ChatLog.ChatLogPulsePeakData | null;
        peak_positive: Domain.Agents.Data.Resources.ChatLog.ChatLogPulsePeakData | null;
        avg_sentiment: number;
        trend: string;
        resolution: boolean;
    };
    export type ChatLogTopicData = {
        id: number;
        topic: string;
        description: string | null;
    };
    export type ChatLogTopicsResourceData = {
        topics: any;
    };
}
declare namespace Domain.Agents.Data.Resources.Stats {
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
    export type ModelFeedbackData = {
        positive: number;
        negative: number;
    };
    export type ModelUsageData = {
        name: string;
        icon: string;
        count: number;
        percentage: number;
        feedback: Domain.Agents.Data.Resources.Stats.ModelFeedbackData;
    };
    export type StatsResourceData = {
        total_messages: number;
        total_conversations: number;
        total_members: number;
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
        feedback: Domain.Agents.Data.Resources.Stats.FeedbackData;
        messages_last_week: Array<Domain.Agents.Data.Resources.Stats.MessageByDayData>;
        model_usage: Array<Domain.Agents.Data.Resources.Stats.ModelUsageData>;
        country_usage: Array<Domain.Agents.Data.Resources.Stats.CountryUsageData>;
        daily_messages: Array<Domain.Agents.Data.Resources.Stats.DailyMessagesData>;
        conversations_trend: Array<Domain.Agents.Data.Resources.Stats.ConversationTrendData>;
        activity_by_hour: Array<Domain.Agents.Data.Resources.Stats.HourlyActivityData>;
        activity_by_day_of_week: Array<Domain.Agents.Data.Resources.Stats.DayOfWeekActivityData>;
        top_knowledge: Array<Domain.Agents.Data.Resources.Stats.TopKnowledgeData>;
        first_data_date: string | null;
        last_updated: string;
    };
    export type TopKnowledgeData = {
        name: string;
        type: string | null;
        accessed: number;
    };
}
declare namespace Domain.Agents.Data.Stats {
    export type AgentMetricsData = {
        totalMessages: number;
        totalConversations: number;
        avgMessagesPerConversation: number | null;
        uniqueSessions: number;
        conversationsWithFeedback: number;
        totalPositiveFeedback: number;
        totalNegativeFeedback: number;
        avgResponseTime: number;
        avgFirstResponseTime: number | null;
        avgConversationDuration: number | null;
        totalTokens: number | null;
        avgTokensPerMessage: number | null;
        modelUsage: Array<any>;
    };
    export type ModelFeedbackData = {
        positive: number;
        negative: number;
    };
    export type ModelUsageData = {
        name: string;
        icon: string | null;
        count: number;
        percentage: number;
        feedback: Domain.Agents.Data.Stats.ModelFeedbackData;
    };
}
declare namespace Domain.Agents.Data.Topics {
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
        counts: any;
    };
    export type TopicsResourceData = {
        topics_by_date: any;
        total_topics: any;
        total_count: number;
    };
}
declare namespace Domain.Auth.Data {
    export type WorkspaceChatterRowData = {
        external_id: string;
        name: string;
        email: string | null;
        status: string;
        agents_count: number;
        created_at: string;
    };
}
declare namespace Domain.Chat.Data {
    export type LlmModelData = {
        id: string;
        internal_id: string;
        name: string;
        description: string;
        icon: string;
        context_window: number;
        features: Array<any>;
        location: string;
        is_reasoning: boolean;
        credits: number;
        is_selected: boolean;
    };
}
declare namespace Domain.Chat.Data.Handoff {
    export type HandoffMarkReadResultData = {
        marked_count: number;
    };
    export type HandoffMessageResultData = {
        success: boolean;
        message: Domain.Chat.Data.Handoff.HandoffMessageResultPayloadData;
    };
    export type HandoffMessageResultPayloadData = {
        id: number;
        content: string;
        role: string;
        created_at: string;
    };
    export type HandoffReturnResultData = {
        success: boolean;
        message: string;
        handoff_status: string;
    };
    export type HandoffTakeoverResultData = {
        success: boolean;
        message: string;
        handoff_status: string;
        user_name: string | null;
        user_email: string | null;
    };
    export type HandoffTypingResultData = {
        success: boolean;
    };
    export type PendingHandoffsResourceData = {
        pending: { [key: number]: any };
        count: number;
    };
}
declare namespace Domain.Connectors.Data {
    export type AccountConnectorRowData = {
        kind: string;
        connector: any | null;
        server: Domain.Agents.Data.Resources.AgentMcpServerData | null;
    };
    export type AccountConnectorsResourceData = {
        featured: { [key: number]: Domain.Connectors.Data.AccountConnectorRowData };
        custom: { [key: number]: Domain.Connectors.Data.AccountConnectorRowData };
    };
    export type AgentConnectorAttachResultData = {
        data: Domain.Agents.Data.Resources.AgentMcpServerData;
        is_attached: boolean;
    };
    export type AgentConnectorRefreshResultData = {
        data: Domain.Agents.Data.Resources.AgentMcpServerData;
        tools_count: number;
    };
    export type AgentConnectorRowData = {
        connector: any;
        state: string;
        server: Domain.Agents.Data.Resources.AgentMcpServerData | null;
    };
    export type AgentConnectorsResourceData = {
        featured: { [key: number]: Domain.Connectors.Data.AgentConnectorRowData };
        custom: { [key: number]: Domain.Connectors.Data.AgentCustomConnectorRowData };
    };
    export type AgentCustomConnectorRowData = {
        id: string;
        name: string;
        is_attached: boolean;
    };
    export type ConnectorToolData = {
        id: number;
        name: string;
        short_name: string;
        description: string | null;
        parameters: any;
        is_enabled: boolean;
    };
    export type ConnectorToolsResourceData = {
        tools: { [key: number]: Domain.Connectors.Data.ConnectorToolData };
        cached_at: string | null;
    };
    export type ToggledConnectorToolData = {
        id: number;
        is_enabled: boolean;
    };
}
declare namespace Domain.Sources.Text.Data {
    export type TrainedTextRowData = {
        id: number;
        excerpt: string;
        word_count: number;
        agents: { [key: number]: { [key: string]: any } };
    };
}
declare namespace Domain.Widget.Data {
    export type WidgetAckData = {
        success: boolean;
    };
    export type WidgetAgentVerifyResultData = {
        verified: boolean;
    };
    export type WidgetEndChatResultData = {
        success: boolean;
        message: string;
    };
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
    export type WidgetMarkReadResultData = {
        marked_count: number;
    };
    export type WidgetSessionInitResultData = {
        session_id: string;
        thread_id: string;
        is_new_session: boolean;
        is_new_thread: boolean;
    };
    export type WidgetSettingsBrandingData = {
        enabled: boolean;
        name: string;
        url: string;
        logo_url: string;
    };
    export type WidgetSettingsResourceData = {
        protected: boolean;
        title: string;
        logo_url: string;
        avatar_url: string | null;
        welcome_message: string | null;
        color_theme: string | null;
        color_shade: string | null;
        bubbles: { [key: number]: any };
        position: string | null;
        show_on_load: boolean | null;
        show_on_load_delay: number | null;
        branding: Domain.Widget.Data.WidgetSettingsBrandingData;
        starters: { [key: number]: any };
        window_size: string | null;
        theme: string | null;
        keyboard_trigger: string | null;
        bar_placement: string | null;
        composer_placeholder: string | null;
        chat_footer_text: string | null;
    };
    export type WidgetThreadStoredData = {
        id: string;
    };
    export type WidgetThreadsResultData = {
        threads: { [key: number]: any };
    };
}
