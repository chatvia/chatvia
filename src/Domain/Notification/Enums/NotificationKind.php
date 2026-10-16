<?php

declare(strict_types=1);

namespace Domain\Notification\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a notification is about. The dashboard picks the sentence and icon by
 * kind, so the stored row holds no text. Stored in `user_notifications.kind`,
 * so the values must not change.
 */
#[TypeScript]
enum NotificationKind: string
{
    case HandoffRequested = 'handoff_requested';
    case SourceTrained = 'source_trained';
    case SourceTrainingFailed = 'source_training_failed';
    case LeadCaptured = 'lead_captured';
    case AttackBlocked = 'attack_blocked';
    case GuardrailSkipped = 'guardrail_skipped';
    case SecuritySettingsChanged = 'security_settings_changed';
    case NewDeviceSignIn = 'new_device_sign_in';
    case MessageLimitWarning = 'message_limit_warning';
    case MessageLimitReached = 'message_limit_reached';
    case RateLimitExceeded = 'rate_limit_exceeded';
    case EvalRunFinished = 'eval_run_finished';
    case DataExportReady = 'data_export_ready';

    public function category(): NotificationCategory
    {
        return match ($this) {
            self::HandoffRequested => NotificationCategory::Handoffs,
            self::AttackBlocked,
            self::GuardrailSkipped,
            self::SecuritySettingsChanged,
            self::NewDeviceSignIn => NotificationCategory::Security,
            self::SourceTrained,
            self::SourceTrainingFailed => NotificationCategory::Training,
            self::LeadCaptured => NotificationCategory::Leads,
            self::MessageLimitWarning,
            self::MessageLimitReached,
            self::RateLimitExceeded => NotificationCategory::Usage,
            self::EvalRunFinished,
            self::DataExportReady => NotificationCategory::Reports,
        };
    }
}
