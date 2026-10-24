<?php

declare(strict_types=1);

namespace App\Admin\Forms;

use Filament\Forms\Components\CheckboxList;
use Filament\Support\Enums\GridDirection;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;

/**
 * A list of models to tick, each label with the model's logo (HTML): one
 * model per row in a box that scrolls, a search over names and ids, and how
 * many are ticked under it. Setup's "Manage models" and Settings → AI models'
 * "Add a model" both use it, so picking models looks the same in each.
 * Styled by the panel theme (`.cv-model-picker`).
 */
class ModelPicker extends CheckboxList
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->hiddenLabel()
            ->allowHtml()
            // One column, row by row: Filament's default fills CSS columns, which
            // in a list of fixed height spill to the side and scroll sideways.
            ->gridDirection(GridDirection::Row)
            ->searchable()
            ->searchPrompt('Search by name or id')
            ->noSearchResultsMessage('No model matches your search.')
            ->belowContent(fn (ModelPicker $component): HtmlString => $component->pickedCount())
            // A long list keeps its height while searching, so the dialog does not jump.
            ->extraAttributes(fn (ModelPicker $component): array => ['class' => count($component->getOptions()) > 8 ? 'cv-model-picker cv-model-picker--long' : 'cv-model-picker']);
    }

    /**
     * How many models are ticked out of all listed, counted in the browser as
     * boxes are ticked.
     */
    private function pickedCount(): HtmlString
    {
        $total = count($this->getOptions());

        return new HtmlString(sprintf(
            '<p class="cv-model-picker__count" x-data x-text="(($wire.get(%s) ?? []).length) + %s"></p>',
            e(Js::from($this->getStatePath())),
            e(Js::from(" of {$total} ".($total === 1 ? 'model' : 'models').' picked')),
        ));
    }
}
