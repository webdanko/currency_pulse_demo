<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAlertRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quote' => ['required', 'string', 'size:3'],
            'operator' => ['required', Rule::in(['above', 'below'])],
            'threshold' => ['required', 'numeric', 'gt:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('quote')) {
            $this->merge(['quote' => strtoupper((string) $this->input('quote'))]);
        }
    }
}
