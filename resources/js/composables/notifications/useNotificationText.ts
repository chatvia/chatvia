/**
 * The words and icon of a notification, in the reader's language. The server
 * stores only the kind and the names (agent, source, form…), so a language
 * change applies to old notifications too. Every key is written out (no
 * built key names), so the i18n check sees them all.
 */
import {
    BadgeCheck,
    Download,
    FileWarning,
    Gauge,
    GraduationCap,
    Headset,
    KeyRound,
    MonitorSmartphone,
    ServerOff,
    ShieldAlert,
    ShieldOff,
    UserPlus,
    type LucideIcon,
} from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';
import type { UserNotification } from './notificationStore';

type NotificationKind = Domain.Notification.Enums.NotificationKind;

export interface NotificationText {
    title: string;
    body: string;
}

export interface NotificationLook {
    icon: LucideIcon;
    // Tailwind classes of the icon's round badge.
    tone: string;
}

const LOOKS: Record<NotificationKind, NotificationLook> = {
    handoff_requested: { icon: Headset, tone: 'bg-amber-500/15 text-amber-600 dark:text-amber-400' },
    source_trained: { icon: GraduationCap, tone: 'bg-sky-500/15 text-sky-600 dark:text-sky-400' },
    source_training_failed: { icon: FileWarning, tone: 'bg-red-500/15 text-red-600 dark:text-red-400' },
    lead_captured: { icon: UserPlus, tone: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' },
    attack_blocked: { icon: ShieldAlert, tone: 'bg-red-500/15 text-red-600 dark:text-red-400' },
    guardrail_skipped: { icon: ShieldOff, tone: 'bg-orange-500/15 text-orange-600 dark:text-orange-400' },
    security_settings_changed: { icon: KeyRound, tone: 'bg-violet-500/15 text-violet-600 dark:text-violet-400' },
    new_device_sign_in: { icon: MonitorSmartphone, tone: 'bg-violet-500/15 text-violet-600 dark:text-violet-400' },
    message_limit_warning: { icon: Gauge, tone: 'bg-orange-500/15 text-orange-600 dark:text-orange-400' },
    message_limit_reached: { icon: Gauge, tone: 'bg-red-500/15 text-red-600 dark:text-red-400' },
    rate_limit_exceeded: { icon: Gauge, tone: 'bg-orange-500/15 text-orange-600 dark:text-orange-400' },
    eval_run_finished: { icon: BadgeCheck, tone: 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-400' },
    data_export_ready: { icon: Download, tone: 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-400' },
    ai_model_unavailable: { icon: ServerOff, tone: 'bg-orange-500/15 text-orange-600 dark:text-orange-400' },
};

export function notificationLook(kind: NotificationKind): NotificationLook {
    return LOOKS[kind] ?? LOOKS.source_trained;
}

/**
 * Why a safety check could not run, in one sentence, or null for a reason
 * the dashboard does not know (older rows hold the guard's name instead).
 * Shared with the Guardrails activity log.
 */
export function skipReason(t: (key: string) => string, failure: string): string | null {
    switch (failure) {
        case 'model_unavailable':
            return t('notifications.skip_reasons.model_unavailable');
        case 'key_refused':
            return t('notifications.skip_reasons.key_refused');
        case 'out_of_credit':
            return t('notifications.skip_reasons.out_of_credit');
        case 'rate_limited':
            return t('notifications.skip_reasons.rate_limited');
        case 'unreachable':
            return t('notifications.skip_reasons.unreachable');
        case 'provider_error':
            return t('notifications.skip_reasons.provider_error');
        case 'no_model':
            return t('notifications.skip_reasons.no_model');
        case 'bad_reply':
            return t('notifications.skip_reasons.bad_reply');
        default:
            return null;
    }
}

export function useNotificationText() {
    const { t } = useI18n();

    function guardName(detail: string | null): string {
        return detail === 'prompt_leak' ? t('notifications.guards.prompt_leak') : t('notifications.guards.prompt_injection');
    }

    function securityChange(detail: string | null): string {
        switch (detail) {
            case 'api_key.created':
                return t('notifications.security_changes.api_key_created');
            case 'policy.ip_allowlist_changed':
                return t('notifications.security_changes.ip_allowlist_changed');
            case 'policy.retention_changed':
                return t('notifications.security_changes.retention_changed');
            case 'policy.webhook_disabled':
                return t('notifications.security_changes.webhook_disabled');
            default:
                return t('notifications.security_changes.policy_changed');
        }
    }

    function describe(notification: UserNotification): NotificationText {
        const { content, count } = notification;
        const vars = {
            agent: content.agent?.name ?? '',
            subject: content.subject ?? '',
            detail: content.detail ?? '',
            actor: content.actor ?? '',
            count,
        };

        switch (notification.kind) {
            case 'handoff_requested':
                return {
                    title: t('notifications.kinds.handoff_requested.title'),
                    body: t('notifications.kinds.handoff_requested.body', vars),
                };
            case 'source_trained':
                return {
                    title: t('notifications.kinds.source_trained.title', vars, count),
                    body: t('notifications.kinds.source_trained.body', vars, count),
                };
            case 'source_training_failed':
                return {
                    title: t('notifications.kinds.source_training_failed.title', vars),
                    body: t('notifications.kinds.source_training_failed.body', vars),
                };
            case 'lead_captured':
                return {
                    title: t('notifications.kinds.lead_captured.title', vars, count),
                    body: t('notifications.kinds.lead_captured.body', vars),
                };
            case 'attack_blocked':
                return {
                    title: t('notifications.kinds.attack_blocked.title', vars, count),
                    body: guardName(content.detail),
                };
            case 'guardrail_skipped': {
                const reason = content.detail ? skipReason(t, content.detail) : null;

                return {
                    title: t('notifications.kinds.guardrail_skipped.title', vars, count),
                    body: reason
                        ? t('notifications.kinds.guardrail_skipped.body_reason', { reason })
                        : t('notifications.kinds.guardrail_skipped.body'),
                };
            }
            case 'security_settings_changed':
                return {
                    title: securityChange(content.detail),
                    body: content.actor
                        ? t('notifications.kinds.security_settings_changed.by', vars)
                        : t('notifications.kinds.security_settings_changed.automatic'),
                };
            case 'new_device_sign_in':
                return {
                    title: t('notifications.kinds.new_device_sign_in.title'),
                    body: content.subject
                        ? t('notifications.kinds.new_device_sign_in.body_device', vars)
                        : t('notifications.kinds.new_device_sign_in.body'),
                };
            case 'message_limit_warning':
                return {
                    title: t('notifications.kinds.message_limit_warning.title'),
                    body: t('notifications.kinds.message_limit_warning.body', vars),
                };
            case 'message_limit_reached':
                return {
                    title: t('notifications.kinds.message_limit_reached.title'),
                    body: t('notifications.kinds.message_limit_reached.body', vars),
                };
            case 'rate_limit_exceeded':
                return {
                    title: t('notifications.kinds.rate_limit_exceeded.title', vars),
                    body: t('notifications.kinds.rate_limit_exceeded.body', vars),
                };
            case 'eval_run_finished':
                return content.detail === 'failed'
                    ? {
                          title: t('notifications.kinds.eval_run_finished.title_failed', vars),
                          body: t('notifications.kinds.eval_run_finished.body_failed'),
                      }
                    : {
                          title: t('notifications.kinds.eval_run_finished.title', vars),
                          body: t('notifications.kinds.eval_run_finished.body', vars),
                      };
            case 'data_export_ready':
                return {
                    title: t('notifications.kinds.data_export_ready.title'),
                    body: t('notifications.kinds.data_export_ready.body'),
                };
            case 'ai_model_unavailable':
                return {
                    title: t('notifications.kinds.ai_model_unavailable.title', vars),
                    body: t('notifications.kinds.ai_model_unavailable.body'),
                };
            default:
                return { title: t('notifications.title'), body: '' };
        }
    }

    return { describe };
}
