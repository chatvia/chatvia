<?php

declare(strict_types=1);

namespace App\Admin\Resources\BillingAddons\Pages;

use App\Admin\Billing\StripeSyncNotification;
use App\Admin\Resources\BillingAddons\BillingAddonResource;
use Domain\Billing\Actions\Catalog\SaveBillingAddonAction;
use Domain\Billing\Data\Catalog\StripeCatalogSyncData;
use Domain\Billing\Exceptions\InvalidBillingCatalogException;
use Domain\Billing\Models\BillingAddon;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/**
 * Edits an add-on through {@see SaveBillingAddonAction}, which also sends it
 * to Stripe: a changed price becomes a new Stripe price for new purchases,
 * while existing subscriptions keep theirs.
 *
 * @property-read BillingAddon $record
 */
class EditBillingAddon extends EditRecord
{
    #[\Override]
    protected static string $resource = BillingAddonResource::class;

    private ?StripeCatalogSyncData $stripeResult = null;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return BillingAddonResource::fill($this->record);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var BillingAddon $record */
        try {
            $saved = app(SaveBillingAddonAction::class)->execute(BillingAddonResource::toData($data), $record);
        } catch (InvalidBillingCatalogException $e) {
            $this->refuse($e);
        }

        $this->stripeResult = $saved->stripe;

        return $saved->addon;
    }

    protected function getSavedNotification(): ?Notification
    {
        return $this->stripeResult instanceof StripeCatalogSyncData
            ? StripeSyncNotification::make($this->stripeResult, 'Add-on saved')
            : parent::getSavedNotification();
    }

    /** Shows why the action refused the change and keeps the form open. */
    private function refuse(InvalidBillingCatalogException $e): never
    {
        Notification::make()->danger()->title('Add-on not saved')->body($e->getMessage())->send();

        throw new Halt;
    }
}
