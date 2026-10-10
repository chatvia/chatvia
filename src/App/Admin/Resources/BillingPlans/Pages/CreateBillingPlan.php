<?php

declare(strict_types=1);

namespace App\Admin\Resources\BillingPlans\Pages;

use App\Admin\Billing\StripeSyncNotification;
use App\Admin\Resources\BillingPlans\BillingPlanResource;
use App\Admin\Resources\BillingPlans\Schemas\BillingPlanForm;
use Domain\Billing\Actions\Catalog\SaveBillingPlanAction;
use Domain\Billing\Data\Catalog\StripeCatalogSyncData;
use Domain\Billing\Exceptions\InvalidBillingCatalogException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/**
 * Creates a plan through {@see SaveBillingPlanAction}, which also sends it to
 * Stripe. The notification says what Stripe got, or why it got nothing.
 */
class CreateBillingPlan extends CreateRecord
{
    #[\Override]
    protected static string $resource = BillingPlanResource::class;

    #[\Override]
    protected static bool $canCreateAnother = false;

    private ?StripeCatalogSyncData $stripeResult = null;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            $saved = app(SaveBillingPlanAction::class)->execute(BillingPlanForm::toData($data, (string) $data['key']));
        } catch (InvalidBillingCatalogException $e) {
            $this->refuse($e);
        }

        $this->stripeResult = $saved->stripe;

        return $saved->plan;
    }

    protected function getCreatedNotification(): ?Notification
    {
        return $this->stripeResult instanceof StripeCatalogSyncData
            ? StripeSyncNotification::make($this->stripeResult, 'Plan created')
            : parent::getCreatedNotification();
    }

    /** Shows why the action refused the change and keeps the form open. */
    private function refuse(InvalidBillingCatalogException $e): never
    {
        Notification::make()->danger()->title('Plan not created')->body($e->getMessage())->send();

        throw new Halt;
    }
}
