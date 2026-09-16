<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'quantity' => ['required', 'integer', 'min:0', 'max:999999'],
            'price' => ['required', 'string', 'regex:/\A(?:0|[1-9][0-9]{0,5})(?:\.[0-9]{1,2})?\z/'],
        ];
    }
}
