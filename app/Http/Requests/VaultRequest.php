<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VaultRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Get the vault being updated (null on create)
        $vaultId = $this->route('vault');

        return [
            // Name: required on create, unique always (ignore self on update)
            'name' => [
                $this->isMethod('post') ? 'required' : 'sometimes',
                'string',
                'max:255',
                Rule::unique('vaults', 'name')->ignore($vaultId),
            ],

            'address'       => 'nullable|string|max:255',
            'balance'       => 'sometimes|required|numeric|min:0',
            'total_racks'   => 'nullable|string|max:50',
            'total_bags'    => 'nullable|json',
            'last_cash_in'  => 'nullable|json',
            'last_cash_out' => 'nullable|json',
            'verifiers'     => 'nullable|json',
            'status'        => 'nullable|json',

            // BLOCK vault_id completely – never allow manual input
            'vault_id' => 'prohibited',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'       => 'The vault name is required.',
            'name.unique'         => 'A vault with this name already exists.',
            'vault_id.prohibited' => 'You are not allowed to set or change vault_id. It is auto-generated.',
            'balance.numeric'     => 'Balance must be a valid number.',
        ];
    }

    // Optional: force-remove vault_id from input
    protected function prepareForValidation()
    {
        $this->merge([
            'vault_id' => null,
        ]);
    }
}
