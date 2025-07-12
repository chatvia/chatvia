<?php

namespace App\Rules;

use App\Support\Models;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ModelRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $allowedModels = app(Models::class)->getAllModelExternalIds();
        if (! in_array($value, $allowedModels->toArray())) {
            $fail('The selected model is invalid.');
        }
    }
}
