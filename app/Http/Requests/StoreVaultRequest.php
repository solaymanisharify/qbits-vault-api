<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVaultRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Vault core validations with database uniqueness checks
            'vault_code'     => ['required', 'integer', 'unique:vaults,vault_code'],
            'name'           => ['required', 'string', 'max:255', 'unique:vaults,name'],
            'address'        => ['required', 'string'],
            'total_racks'    => ['nullable'],
            
            // Optional metrics from payload
            'current_amount' => ['nullable', 'numeric'],
            'bag_limit'      => ['nullable', 'integer', 'min:1'],
            'total_bags'     => ['nullable', 'integer'],

            // Nested bags array validations
            'bags'                           => ['nullable', 'array'],
            'bags.*.barcode'                 => ['required_with:bags', 'string', 'distinct', 'unique:vault_bags,barcode'], 
            'bags.*.bag_identifier_barcode'  => ['required_with:bags', 'string'],
            'bags.*.rack_number'             => ['required_with:bags', 'integer', 'min:1'],
            'bags.*.current_amount'          => ['required_with:bags', 'numeric', 'min:0'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'bags.*.barcode'                 => 'bag barcode',
            'bags.*.bag_identifier_barcode'  => 'bag identifier barcode',
            'bags.*.rack_number'             => 'bag rack number',
            'bags.*.current_amount'          => 'bag amount',
        ];
    }
}