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
advanced_pdf: boolean;
ocr: boolean;
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
label: string;
description: string | null;
permissions: Array<string>;
};
export type PermissionMetaData = {
label: string;
description: string;
requires: Array<string>;
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
