<?php

namespace App\Modules\Orders\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'              => ['required', 'string', 'max:100'],
            'email'             => ['required', 'email', 'max:150'],
            'address'           => ['required', 'string', 'max:255'],
            'phone'             => ['required', 'string', 'max:30'],
            'note'              => ['nullable', 'string', 'max:500'],
            'items'             => ['required', 'array', 'min:1'],
            'items.*.product_id'=> ['required', 'integer', 'exists:products,id'],
            'items.*.quantity'  => ['required', 'integer', 'min:1'],
        ];
    }
}
