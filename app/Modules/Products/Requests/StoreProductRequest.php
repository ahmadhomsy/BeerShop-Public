<?php

namespace App\Modules\Products\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'               => ['required', 'string', 'max:150'],
            'namear'             => ['nullable', 'string', 'max:150'],
            'price'              => ['required', 'numeric', 'min:0'],
            'quantity'           => ['nullable', 'integer', 'min:0'],
            'description'        => ['nullable', 'string'],
            'category_id'        => ['required', 'integer', 'exists:categories,id'],
            'second_category_id' => ['nullable', 'integer', 'exists:second_categories,id'],
            'image'              => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
        ];
    }
}
