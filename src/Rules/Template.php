<?php

namespace Comhon\TemplateRenderer\Rules;

use Comhon\TemplateRenderer\Facades\Template as FacadesTemplate;
use Illuminate\Contracts\Validation\ValidationRule;

class Template implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate($attribute, $value, $fail): void
    {
        try {
            if (is_string($value)) {
                FacadesTemplate::validate($value);
            } else {
                $fail("The value $attribute must be a string");
            }
        } catch (\Exception $e) {
            $fail($e->getMessage());
        }
    }
}
