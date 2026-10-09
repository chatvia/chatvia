<?php

namespace Domain\Billing\Data\Stripe;

use Domain\Billing\Enums\Addon;
use Domain\Billing\Enums\MessageCreditAddon;
use Domain\Billing\Enums\PurchaseType;
use Domain\Billing\Enums\RegistrationType;

/**
 * Typed view of the `metadata` we attach to Stripe checkouts, invoices and
 * subscriptions. Stripe stores every metadata value as a string, so flags such
 * as `is_yearly` and `pending_change` arrive as 'true' / 'false'; this object
 * turns them into real types once, at the edge, so no caller compares raw
 * strings.
 *
 * The key constants are the contract between the code that writes the metadata
 * (checkout and plan-change Actions) and the webhook that reads it back.
 */
final readonly class StripeMetadata
{
    public const string REGISTRATION_TYPE = 'registration_type';

    public const string TYPE = 'type';

    public const string ID = 'id';

    public const string ADDON = 'addon';

    public const string PACKAGE = 'package';

    public const string FROM_PACKAGE = 'from_package';

    public const string TO_PACKAGE = 'to_package';

    public const string IS_YEARLY = 'is_yearly';

    public const string PENDING_CHANGE = 'pending_change';

    public const string SCHEDULE_ID = 'schedule_id';

    public const string MESSAGES = 'messages';

    public const string QUANTITY = 'quantity';

    public const string USER_IP = 'user_ip';

    /** Stripe's string form of a true flag. */
    public const string TRUE = 'true';

    /** Stripe's string form of a false flag. */
    public const string FALSE = 'false';

    /**
     * @param  array<string, mixed>  $values
     */
    public function __construct(private array $values = []) {}

    /**
     * Accepts the metadata as it comes out of a decoded webhook payload or a
     * Stripe SDK object (`StripeObject::toArray()`); anything else is empty.
     */
    public static function from(mixed $metadata): self
    {
        return new self(is_array($metadata) ? $metadata : []);
    }

    public function registrationType(): ?RegistrationType
    {
        return RegistrationType::tryFrom($this->string(self::REGISTRATION_TYPE) ?? '');
    }

    public function purchaseType(): ?PurchaseType
    {
        return PurchaseType::tryFrom($this->string(self::TYPE) ?? '');
    }

    /** The one-off credit pack this checkout or invoice paid for, if any. */
    public function messageCreditAddon(): ?MessageCreditAddon
    {
        return MessageCreditAddon::tryFrom($this->string(self::ID) ?? '');
    }

    /**
     * The recurring add-on this subscription is for. Read from our own `addon`
     * key first, then from `type`, which Cashier's Checkout overwrites with the
     * Cashier subscription type (the {@see Addon} value).
     */
    public function subscriptionAddon(): ?Addon
    {
        return Addon::tryFrom($this->string(self::ADDON) ?? '')
            ?? Addon::tryFrom($this->string(self::TYPE) ?? '');
    }

    /** Whether this metadata belongs to an add-on subscription rather than the plan. */
    public function isAddonSubscription(): bool
    {
        return array_key_exists(self::ADDON, $this->values)
            || $this->purchaseType() === PurchaseType::AddonSubscription
            || $this->subscriptionAddon() instanceof Addon;
    }

    /** The plan a trial checkout signed up for; starter when it is missing. */
    public function package(): string
    {
        return $this->string(self::PACKAGE) ?? (string) config('billing.default_package', 'starter');
    }

    /** The plan a scheduled change moves to (or the original package), if recorded. */
    public function targetPackage(): ?string
    {
        return $this->string(self::TO_PACKAGE) ?? $this->string(self::PACKAGE);
    }

    public function isYearly(): bool
    {
        return $this->string(self::IS_YEARLY) === self::TRUE;
    }

    public function hasPendingChange(): bool
    {
        return $this->string(self::PENDING_CHANGE) === self::TRUE;
    }

    public function scheduleId(): ?string
    {
        return $this->string(self::SCHEDULE_ID);
    }

    /** Messages a one-off credit pack adds. */
    public function messages(): int
    {
        return (int) ($this->values[self::MESSAGES] ?? 0);
    }

    public function userIp(): ?string
    {
        return $this->string(self::USER_IP);
    }

    /** Whether the metadata carries the key at all (Stripe drops keys set to null). */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->values;
    }

    /** Converts a PHP bool to Stripe's string flag. */
    public static function flag(bool $value): string
    {
        return $value ? self::TRUE : self::FALSE;
    }

    private function string(string $key): ?string
    {
        $value = $this->values[$key] ?? null;

        if (is_bool($value)) {
            return self::flag($value);
        }

        if (! is_scalar($value) || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
