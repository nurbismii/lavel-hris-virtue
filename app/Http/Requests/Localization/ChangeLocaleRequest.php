<?php

namespace App\Http\Requests\Localization;

use App\Services\Localization\LocaleService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeLocaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', Rule::in(app(LocaleService::class)->supportedLocaleCodes())],
        ];
    }

    public function validationData(): array
    {
        return array_merge($this->all(), [
            'locale' => $this->route('locale'),
        ]);
    }
}
