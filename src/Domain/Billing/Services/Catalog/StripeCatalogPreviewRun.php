<?php

declare(strict_types=1);

namespace Domain\Billing\Services\Catalog;

use Domain\Billing\Actions\Catalog\PreviewBillingCatalogSyncAction;

/**
 * A sync run that only reads Stripe: it compares the saved plans and add-ons
 * with the live products and prices and logs what a real sync would create or
 * update, without sending any write. It answers a "would create" with a
 * placeholder id. Used by "Check against Stripe"
 * ({@see PreviewBillingCatalogSyncAction}) so an admin can see, before a sync,
 * whether any saved amount differs from what Stripe charges.
 */
final class StripeCatalogPreviewRun extends StripeCatalogSyncRun
{
    private int $placeholders = 0;

    #[\Override]
    protected function sendProductCreate(array $params, array $options): string
    {
        return $this->placeholder('product');
    }

    #[\Override]
    protected function sendProductUpdate(string $productId, array $params): void {}

    #[\Override]
    protected function sendPriceCreate(array $params, array $options): string
    {
        return $this->placeholder('price');
    }

    private function placeholder(string $object): string
    {
        return "new_{$object}_".++$this->placeholders;
    }
}
