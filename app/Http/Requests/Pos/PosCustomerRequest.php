<?php

namespace App\Http\Requests\Pos;

use App\Models\PosCustomerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PosCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'             => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'string',
                'max:255',
                Rule::unique('pos_customers')
                    ->where('company_id', $this->user()->company_id)
                    ->ignore($this->customer?->id),
            ],
            'address'          => ['sometimes', 'nullable', 'string'],
            'phone'            => ['sometimes', 'nullable', 'string', 'max:20'],
            'customer_type_uuid' => [
                $this->isMethod('POST') ? 'required' : 'sometimes',
                'string',
                'uuid',
                function ($attribute, $value, $fail) {
                    if (!$value) return; // nullable

                    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) {
                        return; // uuid rule handles error
                    }

                    $customerTypeExists = PosCustomerType::where('uuid', $value)
                        ->where('company_id', $this->user()->company_id)
                        ->exists();

                    if (!$customerTypeExists) {
                        $fail(__('pos.customers.validation.customer_type_not_found'));
                    }
                }
            ],
        ];
    }

    public function getCustomerTypeId(): ?int
    {
        if (!$this->customer_type_uuid) return null;

        return PosCustomerType::where('uuid', $this->customer_type_uuid)
            ->where('company_id', $this->user()->company_id)
            ->value('id');
    }

    public function messages(): array
    {
        return [
            'name.required'              => __('pos.customers.validation.name_required'),
            'name.unique'                => __('pos.customers.validation.name_unique'),
            'phone.max'                  => __('pos.customers.validation.phone_max'),
            'customer_type_uuid.uuid'    => __('pos.customers.validation.customer_type_uuid_invalid'),
        ];
    }
}
