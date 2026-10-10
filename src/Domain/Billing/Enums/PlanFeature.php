<?php

declare(strict_types=1);

namespace Domain\Billing\Enums;

use Domain\Billing\Contracts\Entitlements;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One kind of feature bullet on a plan card. The dashboard renders it with
 * the vue-i18n message `billing.features.<value>` in every language, so a
 * value must never change without renaming that message in every locale.
 *
 * The limit cases take a `count` and come in up to three wordings: the number
 * ("3,000 messages per month"), "unlimited" for a limit of
 * {@see Entitlements::UNLIMITED} or more, and the wording of a plan sold
 * through sales only, whose limits are agreed per contract.
 */
#[TypeScript]
enum PlanFeature: string
{
    // Limits.
    case MessagesPerMonth = 'messages_per_month';
    case MessagesPerYear = 'messages_per_year';
    case MessagesUnlimited = 'messages_unlimited';
    case MessagesCustom = 'messages_custom';
    case Agents = 'agents';
    case AgentsUnlimited = 'agents_unlimited';
    case Pages = 'pages';
    case PagesUnlimited = 'pages_unlimited';
    case PagesCustom = 'pages_custom';
    case Links = 'links';
    case LinksUnlimited = 'links_unlimited';
    case LinksCustom = 'links_custom';
    case Minutes = 'minutes';
    case MinutesUnlimited = 'minutes_unlimited';
    case MinutesCustom = 'minutes_custom';
    case CustomDomains = 'custom_domains';
    case CustomDomainsUnlimited = 'custom_domains_unlimited';
    case CustomDomainsCustom = 'custom_domains_custom';
    case Teams = 'teams';
    case TeamsUnlimited = 'teams_unlimited';
    case Members = 'members';
    case MembersUnlimited = 'members_unlimited';
    case Retention = 'retention';
    case RetentionUnlimited = 'retention_unlimited';
    case RetentionCustom = 'retention_custom';

    // Feature switches of the plan (its capabilities).
    case Sentiment = 'sentiment';
    case Tone = 'tone';
    case Topics = 'topics';
    case Pulse = 'pulse';
    case Handoff = 'handoff';
    case Api = 'api';
    case Evals = 'evals';
    case Guardrails = 'guardrails';
    case Audit = 'audit';
    case Branding = 'branding';
    case NetworkAccess = 'network_access';
    case SiemWebhook = 'siem_webhook';

    // Included in every plan.
    case Mcp = 'mcp';
    case FunctionCalling = 'function_calling';

    // Highlights an admin can tick on a plan for what it offers outside the
    // app; see highlights().
    case CustomIntegrations = 'custom_integrations';
    case EuDataResidency = 'eu_data_residency';
    case SsoSaml = 'sso_saml';
    case UptimeSla = 'uptime_sla';
    case DedicatedSupport = 'dedicated_support';
    case CustomContract = 'custom_contract';

    /**
     * The bullets an admin can add to a plan by hand (stored in the plan's
     * `highlights`), in card order. They are translated like every other
     * bullet, unlike the plan's free-text lines.
     *
     * @return list<self>
     */
    public static function highlights(): array
    {
        return [
            self::CustomIntegrations,
            self::EuDataResidency,
            self::SsoSaml,
            self::UptimeSla,
            self::DedicatedSupport,
            self::CustomContract,
        ];
    }

    /** The wording of this limit when it has no limit. */
    public function unlimited(): self
    {
        return match ($this) {
            self::MessagesPerMonth, self::MessagesPerYear => self::MessagesUnlimited,
            self::Agents => self::AgentsUnlimited,
            self::Pages => self::PagesUnlimited,
            self::Links => self::LinksUnlimited,
            self::Minutes => self::MinutesUnlimited,
            self::CustomDomains => self::CustomDomainsUnlimited,
            self::Teams => self::TeamsUnlimited,
            self::Members => self::MembersUnlimited,
            self::Retention => self::RetentionUnlimited,
            default => $this,
        };
    }

    /**
     * The wording of this limit on a plan sold through sales only: "custom"
     * where the amount is agreed per contract, "unlimited" for agents and
     * people.
     */
    public function forContactSales(): self
    {
        return match ($this) {
            self::MessagesPerMonth, self::MessagesPerYear => self::MessagesCustom,
            self::Agents => self::AgentsUnlimited,
            self::Pages => self::PagesCustom,
            self::Links => self::LinksCustom,
            self::Minutes => self::MinutesCustom,
            self::CustomDomains => self::CustomDomainsCustom,
            self::Teams => self::TeamsUnlimited,
            self::Members => self::MembersUnlimited,
            self::Retention => self::RetentionCustom,
            default => $this,
        };
    }
}
