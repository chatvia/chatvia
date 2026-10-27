<?php

declare(strict_types=1);

namespace App\Admin\Forms;

use Closure;
use Filament\Forms\Components\Radio;
use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;

use function Filament\Support\generate_icon_html;

/**
 * A radio list drawn as cards: each choice with its picture (a logo URL or
 * an icon), its name, a short description and small tags. A real radio
 * input stays inside each card, so the keyboard and screen readers work as
 * with the plain list. Styled by the panel theme (`.cv-choices`).
 */
class CardRadio extends Radio
{
    /** @var array<string, string|Heroicon>|Closure Logo URL or icon, by option. */
    protected array|Closure $icons = [];

    /** @var array<string, list<string>>|Closure Small labels under the description, by option. */
    protected array|Closure $tags = [];

    /**
     * @param  array<string, string|Heroicon>|Closure  $icons
     */
    public function icons(array|Closure $icons): static
    {
        $this->icons = $icons;

        return $this;
    }

    /**
     * @param  array<string, list<string>>|Closure  $tags
     */
    public function tags(array|Closure $tags): static
    {
        $this->tags = $tags;

        return $this;
    }

    /**
     * @return array<string, string|Heroicon>
     */
    public function getIcons(): array
    {
        return $this->evaluate($this->icons);
    }

    /**
     * @return array<string, list<string>>
     */
    public function getTags(): array
    {
        return $this->evaluate($this->tags);
    }

    #[\Override]
    public function toEmbeddedHtml(): string
    {
        $id = $this->getId();
        $isDisabled = $this->isDisabled();
        $statePath = $this->getStatePath();
        $hasError = $this->hasErrorForPath($statePath);
        $wireModelAttribute = $this->applyStateBindingModifiers('wire:model');
        $isAutofocused = $this->isAutofocused();
        $icons = $this->getIcons();
        $tags = $this->getTags();
        $check = generate_icon_html(Heroicon::Check, size: IconSize::Small)?->toHtml();

        $containerAttributes = $this->getExtraAttributeBag()
            ->merge([
                ...$this->getAccessibilityAttributes(),
                'aria-labelledby' => filled($this->getLabel()) ? e("{$id}-label") : null,
                'aria-required' => $this->isRequired() && (! $isDisabled) ? 'true' : null,
                'role' => 'radiogroup',
            ], escape: false)
            ->class(['cv-choices']);

        ob_start(); ?>

        <div <?= $containerAttributes->toHtml() ?>>
            <?php $first = true; ?>
            <?php foreach ($this->getOptions() as $value => $label) { ?>
                <?php
                    $inputAttributes = $this->getExtraInputAttributeBag()
                        ->merge($this->getAccessibilityAttributes(), escape: false)
                        ->merge([
                            'autofocus' => $first && $isAutofocused,
                            'disabled' => $isDisabled || $this->isOptionDisabled($value, $label),
                            'id' => e("{$id}-{$value}"),
                            'name' => $id,
                            'required' => $this->isRequired() && (! $isDisabled) && (! $this->isOptionDisabled($value, $label)),
                            'value' => e($value),
                            $wireModelAttribute => $statePath,
                        ], escape: false)
                        ->class([
                            'cv-choice__input',
                            'fi-invalid' => $hasError,
                        ]);
                $first = false;
                ?>

                <label class="cv-choice">
                    <input type="radio" <?= $inputAttributes->toHtml() ?> />

                    <?php if (isset($icons[$value])) { ?>
                        <span class="cv-choice__icon<?= $icons[$value] instanceof Heroicon ? '' : ' cv-choice__icon--logo' ?>">
                            <?= generate_icon_html($icons[$value], size: IconSize::Large)?->toHtml() ?>
                        </span>
                    <?php } ?>

                    <span class="cv-choice__text">
                        <span class="cv-choice__title"><?= e($label) ?></span>

                        <?php if ($this->hasDescription($value)) { ?>
                            <span class="cv-choice__description"><?= e($this->getDescription($value)) ?></span>
                        <?php } ?>

                        <?php if (($tags[$value] ?? []) !== []) { ?>
                            <span class="cv-chips">
                                <?php foreach ($tags[$value] as $tag) { ?>
                                    <span class="cv-chip"><?= e($tag) ?></span>
                                <?php } ?>
                            </span>
                        <?php } ?>
                    </span>

                    <span class="cv-choice__check" aria-hidden="true"><?= $check ?></span>
                </label>
            <?php } ?>
        </div>

        <?php return $this->wrapEmbeddedHtml((string) ob_get_clean(), labelTag: 'div');
    }
}
