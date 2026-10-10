<?php

declare(strict_types=1);

namespace Domain\Billing\Exceptions;

use Domain\Billing\Actions\Catalog\ArchiveBillingPlanAction;
use Domain\Billing\Actions\Catalog\SaveBillingAddonAction;
use Domain\Billing\Actions\Catalog\SaveBillingPlanAction;
use RuntimeException;

/**
 * Thrown when a plan or add-on cannot be saved or archived as asked
 * ({@see SaveBillingPlanAction}, {@see ArchiveBillingPlanAction},
 * {@see SaveBillingAddonAction}): a bad or taken key, a key change, a
 * negative price, or a change that would leave no default plan. The message
 * is meant for the admin who asked for it.
 */
class InvalidBillingCatalogException extends RuntimeException
{
    public static function invalidKey(string $key): self
    {
        return new self("The key \"{$key}\" is not valid: use lower-case letters, digits and underscores, starting with a letter.");
    }

    public static function keyTaken(string $key): self
    {
        return new self("A plan with the key \"{$key}\" already exists.");
    }

    public static function keyChanged(): self
    {
        return new self('The key of a plan cannot change: it is stored in Stripe and on every workspace.');
    }

    public static function archivedDefault(): self
    {
        return new self('An archived plan cannot be the default plan.');
    }

    public static function defaultCannotBeArchived(): self
    {
        return new self('The default plan cannot be archived. Make another plan the default first.');
    }

    public static function defaultRequired(): self
    {
        return new self('One plan must be the default plan. To change it, make another plan the default.');
    }

    public static function negativePrice(): self
    {
        return new self('A price cannot be negative.');
    }
}
