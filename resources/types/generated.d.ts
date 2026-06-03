declare namespace App.Billing.Data.Resources.Package {
    export type BillingCurrentPackageData = {
        id: string;
        name: string;
        description: string;
        features: App.Billing.Data.Resources.Package.BillingPackageFeaturesData;
        monthly_price: number;
        monthly_price_formatted: string;
        yearly_price: number;
        yearly_price_formatted: string;
        is_yearly: boolean;
        is_popular: boolean;
        limits: App.Billing.Data.Resources.Quota.BillingQuotaLimitsData;
        capabilities: App.Billing.Data.Resources.Package.BillingPackageCapabilitiesData;
    };
    export type BillingPackageCapabilitiesData = {
        sentiment: boolean;
        tone: boolean;
        topics: boolean;
        handoff: boolean;
        api: boolean;
        pulse: boolean;
        retentionDays: number;
    };
    export type BillingPackageFeaturesData = {
        monthly: { [key: number]: string } | Array<any>;
        yearly: { [key: number]: string } | Array<any>;
    };
}
declare namespace App.Billing.Data.Resources.Quota {
    export type BillingQuotaFixedData = {
        agents_limit: number;
        pages_limit: number;
        minutes_limit: number;
        links_limit: number;
        custom_domains_limit: number;
        workspace_users_limit: number;
    };
    export type BillingQuotaLimitsData = {
        monthly: App.Billing.Data.Resources.Quota.BillingQuotaPeriodData;
        yearly: App.Billing.Data.Resources.Quota.BillingQuotaPeriodData;
        fixed: App.Billing.Data.Resources.Quota.BillingQuotaFixedData;
    };
    export type BillingQuotaPeriodData = {
        messages_limit: number;
    };
}
declare namespace App.Billing.Data.Resources.Subscription {
    export type BillingAddonMessageCreditsData = {
        manual_messages: number;
        auto_recharge_messages: number;
    };
    export type BillingAddonSubscriptionData = {
        active: boolean;
        quantity: number;
        cancels_at: string | null;
    };
    export type BillingAddonsData = {
        auto_recharge: App.Billing.Data.Resources.Subscription.BillingAutoRechargeData;
        extra_message_credits: App.Billing.Data.Resources.Subscription.BillingAddonMessageCreditsData;
        extra_agents: App.Billing.Data.Resources.Subscription.BillingAddonSubscriptionData;
        custom_domains: App.Billing.Data.Resources.Subscription.BillingAddonSubscriptionData;
    };
    export type BillingAutoRechargeData = {
        enabled: boolean;
        threshold: number;
        amount: number;
        last_triggered_at: string | null;
    };
    export type BillingPendingChangeData = {
        from_package: string | null;
        to_package: string | null;
        is_yearly: boolean | null;
        effective_date: number | null;
        scheduled_at: string | null;
        has_pending_update: boolean;
    };
    export type BillingResourceData = {
        current_package: App.Billing.Data.Resources.Package.BillingCurrentPackageData;
        on_trial: boolean;
        trial_ends_at: string | null;
        subscription: App.Billing.Data.Resources.Subscription.BillingSubscriptionData | null;
        subscribed: boolean;
        subscription_canceled: boolean;
        pending_plan_change: App.Billing.Data.Resources.Subscription.BillingPendingChangeData | null;
        addons: App.Billing.Data.Resources.Subscription.BillingAddonsData;
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
declare namespace App.Chatter.Data {
    export type WorkspaceChatterRowData = {
        external_id: string;
        name: string;
        email: string | null;
        status: string;
        agents_count: number;
        created_at: string;
    };
}
declare namespace App.Team.Data.Resources.Members {
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
declare namespace App.Team.Data.Resources.Permissions {
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
}
declare namespace App.Team.Data.Resources.Roles {
    export type RoleConfigData = {
        name: string;
        description: string | null;
        permissions: Array<string>;
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
declare namespace App.Team.Data.Resources.Team {
    export type TeamAgentData = {
        id: number;
        external_id: string;
        name: string;
    };
    export type TeamData = {
        id: number;
        name: string;
        owner: App.Team.Data.Resources.Team.TeamOwnerData;
    };
    export type TeamListItemData = {
        id: number;
        name: string;
        owner: App.Team.Data.Resources.Team.TeamOwnerData;
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
declare namespace App.Workspace.Data.Resources.My.Usage {
    export type UsageResourceData = {
        subscription: App.Workspace.Data.Resources.My.Usage.UsageSubscriptionData;
        agents: App.Workspace.Data.Resources.My.Usage.UsageStatsData;
        pages: App.Workspace.Data.Resources.My.Usage.UsageStatsData;
        minutes: App.Workspace.Data.Resources.My.Usage.UsageStatsData;
        links: App.Workspace.Data.Resources.My.Usage.UsageStatsData;
        messages: App.Workspace.Data.Resources.My.Usage.UsageStatsData;
        teams: App.Workspace.Data.Resources.My.Usage.UsageStatsData;
        members: App.Workspace.Data.Resources.My.Usage.UsageStatsData;
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
declare namespace Domain.Agent.Data.Resources.Account {
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
        leads_mcp_enabled: boolean;
    };
}
declare namespace Domain.Agent.Data.Resources.ChatLog.Conversations {
    export type ChatLogConversationData = {
        conversation_id: string;
        last_at: string;
        expires_at: string | null;
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
}
declare namespace Domain.Agent.Data.Resources.ChatLog.Countries {
    export type ChatLogCountriesResourceData = {
        countries: { [key: number]: Domain.Agent.Data.Resources.ChatLog.Countries.ChatLogCountryData };
    };
    export type ChatLogCountryData = {
        id: number;
        code: string;
        name: string;
    };
}
declare namespace Domain.Agent.Data.Resources.ChatLog.Handoff {
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
declare namespace Domain.Agent.Data.Resources.ChatLog.Messages {
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
        lead: Domain.Agent.Data.Resources.ChatLog.Handoff.ChatLogLeadData | null;
        handoff: Domain.Agent.Data.Resources.ChatLog.Handoff.ChatLogHandoffStatusData;
    };
}
declare namespace Domain.Agent.Data.Resources.ChatLog.Pulse {
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
        messages: { [key: number]: Domain.Agent.Data.Resources.ChatLog.Pulse.ChatLogPulseMessageData };
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
declare namespace Domain.Agent.Data.Resources.ChatLog.Topics {
    export type ChatLogTopicData = {
        id: number;
        topic: string;
        description: string | null;
    };
    export type ChatLogTopicsResourceData = {
        topics: any;
    };
}
declare namespace Domain.Agent.Data.Resources.Llm {
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
}
declare namespace Domain.Agent.Data.Resources.Sources {
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
declare namespace Domain.Agent.Data.Resources.Stats {
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
        feedback: Domain.Agent.Data.Resources.Stats.ModelFeedbackData;
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
        feedback: Domain.Agent.Data.Resources.Stats.FeedbackData;
        messages_last_week: Array<Domain.Agent.Data.Resources.Stats.MessageByDayData>;
        model_usage: Array<Domain.Agent.Data.Resources.Stats.ModelUsageData>;
        country_usage: Array<Domain.Agent.Data.Resources.Stats.CountryUsageData>;
        daily_messages: Array<Domain.Agent.Data.Resources.Stats.DailyMessagesData>;
        conversations_trend: Array<Domain.Agent.Data.Resources.Stats.ConversationTrendData>;
        activity_by_hour: Array<Domain.Agent.Data.Resources.Stats.HourlyActivityData>;
        activity_by_day_of_week: Array<Domain.Agent.Data.Resources.Stats.DayOfWeekActivityData>;
        top_knowledge: Array<Domain.Agent.Data.Resources.Stats.TopKnowledgeData>;
        first_data_date: string | null;
        last_updated: string;
    };
    export type TopKnowledgeData = {
        name: string;
        type: string | null;
        accessed: number;
    };
}
declare namespace Domain.Agent.Data.Sources {
    export type KnowledgeAnalyzeResourceData = {
        length: number;
        mime_type: string;
        filetype: string;
        is_audio_file: boolean;
        is_video_file: boolean;
    };
}
declare namespace Domain.Agent.Data.Stats {
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
        feedback: Domain.Agent.Data.Stats.ModelFeedbackData;
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
declare namespace Domain.Connector.Data.Account {
    export type AccountConnectorRowData = {
        kind: string;
        connector: any | null;
        server: Domain.Mcp.Data.Resources.AgentMcpServerData | null;
    };
    export type AccountConnectorsResourceData = {
        featured: { [key: number]: Domain.Connector.Data.Account.AccountConnectorRowData };
        custom: { [key: number]: Domain.Connector.Data.Account.AccountConnectorRowData };
    };
}
declare namespace Domain.Connector.Data.Agent {
    export type AgentConnectorAttachResultData = {
        data: Domain.Mcp.Data.Resources.AgentMcpServerData;
        is_attached: boolean;
    };
    export type AgentConnectorRefreshResultData = {
        data: Domain.Mcp.Data.Resources.AgentMcpServerData;
        tools_count: number;
    };
    export type AgentConnectorRowData = {
        connector: any;
        state: string;
        server: Domain.Mcp.Data.Resources.AgentMcpServerData | null;
    };
    export type AgentConnectorsResourceData = {
        featured: { [key: number]: Domain.Connector.Data.Agent.AgentConnectorRowData };
        custom: { [key: number]: Domain.Connector.Data.Custom.AgentCustomConnectorRowData };
    };
}
declare namespace Domain.Connector.Data.Catalog {
    export type ConnectorToolData = {
        id: number;
        name: string;
        short_name: string;
        description: string | null;
        parameters: any;
        is_enabled: boolean;
    };
    export type ConnectorToolsResourceData = {
        tools: { [key: number]: Domain.Connector.Data.Catalog.ConnectorToolData };
        cached_at: string | null;
    };
}
declare namespace Domain.Connector.Data.Custom {
    export type AgentCustomConnectorRowData = {
        id: string;
        name: string;
        is_attached: boolean;
    };
}
declare namespace Domain.Function.Data.Resources {
    export type AgentFunctionAttachResultData = {
        data: Domain.Function.Data.Resources.AgentFunctionData;
        is_attached: boolean;
    };
    export type AgentFunctionData = {
        external_id: string;
        internal_name: string;
        display_name: string | null;
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
        internal_name: string;
        display_name: string | null;
        description: string | null;
        is_enabled: boolean;
        is_attached: boolean;
    };
    export type AgentFunctionsListResourceData = {
        data: { [key: number]: Domain.Function.Data.Resources.AgentFunctionRowData };
    };
}
declare namespace Domain.Lead.Data.Resources {
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
}
declare namespace Domain.Llm.Data {
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
declare namespace Domain.Mcp.Data {
    export type ToggledConnectorToolData = {
        id: number;
        is_enabled: boolean;
    };
}
declare namespace Domain.Mcp.Data.Resources {
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
}
declare namespace Domain.Member.Data.Resources {
    export type AgentMemberRowData = {
        external_id: string;
        name: string;
        email: string | null;
        status: string;
        has_access: boolean;
    };
    export type AgentMembershipAssignmentData = {
        attached: { [key: number]: Domain.Member.Data.Resources.AgentMembershipRowData };
        available: { [key: number]: Domain.Member.Data.Resources.AgentMembershipRowData };
    };
    export type AgentMembershipRowData = {
        id: string;
        name: string;
    };
}
declare namespace Domain.Prompt.Data.Resources {
    export type AgentPromptPickerData = {
        id: string;
        name: string;
        prompt: string;
    };
    export type AgentPromptPickerListData = {
        data: { [key: number]: Domain.Prompt.Data.Resources.AgentPromptPickerData };
    };
    export type AgentPromptRowData = {
        id: string;
        name: string;
        prompt: string;
        agents_count: number;
        created_at: string | null;
    };
}
declare namespace Domain.Source.Text.Data {
    export type TrainedTextRowData = {
        id: number;
        excerpt: string;
        word_count: number;
        agents: { [key: number]: { [key: string]: any } };
    };
}
declare namespace Domain.Topic.Data {
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
declare namespace Domain.Widget.Data.Auth {
    export type WidgetAckData = {
        success: boolean;
    };
    export type WidgetAgentVerifyResultData = {
        verified: boolean;
    };
}
declare namespace Domain.Widget.Data.Handoff {
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
declare namespace Domain.Widget.Data.Session {
    export type WidgetEndChatResultData = {
        success: boolean;
        message: string;
    };
    export type WidgetSessionInitResultData = {
        session_id: string;
        thread_id: string;
        is_new_session: boolean;
        is_new_thread: boolean;
    };
}
declare namespace Domain.Widget.Data.Settings {
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
        branding: Domain.Widget.Data.Settings.WidgetSettingsBrandingData;
        starters: { [key: number]: any };
        window_size: string | null;
        theme: string | null;
        keyboard_trigger: string | null;
        bar_placement: string | null;
        composer_placeholder: string | null;
        chat_footer_text: string | null;
        handoff_enabled: boolean;
    };
}
declare namespace Domain.Widget.Data.Thread {
    export type WidgetMarkReadResultData = {
        marked_count: number;
    };
    export type WidgetThreadStoredData = {
        id: string;
    };
    export type WidgetThreadsResultData = {
        threads: { [key: number]: any };
    };
}
