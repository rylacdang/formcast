<?php

declare(strict_types=1);

namespace Workbench\App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Workbench\App\Enums\PaymentMethod;

final class StoreOrderRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_email' => ['required', 'email', 'max:255', 'unique:orders,customer_email'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'gcash_reference' => ['nullable', 'required_if:payment_method,gcash', 'string', 'size:13'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'gcash_reference' => 'GCash reference number',
        ];
    }
}
