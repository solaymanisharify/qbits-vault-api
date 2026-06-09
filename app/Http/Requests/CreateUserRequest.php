<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
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
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email:rfc,dns|max:255|unique:users,email', 
            'password' => 'required|string|min:6',
            'role'     => 'required|array|min:1',
            'role.*'   => 'integer|exists:roles,id', 
        ];
    }

    /**
     * Custom error messages for validation.
     */
    public function messages(): array
    {
        return [
            'email.email'     => 'The email address must be a valid format with an active DNS record.',
            'email.unique'    => 'This email address has already been taken.',
            'password.min'    => 'The password must be at least 6 characters long.',
            'role.*.exists'   => 'One or more selected roles are invalid.',
        ];
    }
}