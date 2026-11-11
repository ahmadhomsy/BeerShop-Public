<?php

namespace App\Modules\Products\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'               => ['sometimes', 'required', 'string', 'max:150'],
            'namear'             => ['nullable', 'string', 'max:150'],
            'price'              => ['sometimes', 'required', 'numeric', 'min:0'],
            'description'        => ['nullable', 'string'],
            'category_id'        => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'second_category_id' => ['nullable', 'integer', 'exists:second_categories,id'],
            'image'              => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:4096'],
        ];
    }
}
